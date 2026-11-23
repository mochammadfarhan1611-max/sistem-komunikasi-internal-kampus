<?php

namespace App\Services\Unuja;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnujaPerwalianSyncService
{
    public function __construct(
        private UnujaApiClient $apiClient,
        private UnujaProfileService $profileService,
    ) {}

    /**
     * Sinkronisasi otomatis Grup Perwalian / Bimbingan Akademik (Dosen Wali / PA) dari API Kampus (`filter: mahasiswa` & `filter: dosen`).
     * Menggunakan field `id_wali`, `wali`, dan `nidn` dari endpoint `filter: mahasiswa`.
     *
     * @return array{groups_synced: int, students_assigned: int, advisors_count: int}
     */
    public function syncPerwalianGroupsFromApi(string $semester = '20261', ?callable $logger = null): array
    {
        $log = function (string $msg) use ($logger): void {
            if ($logger !== null) {
                $logger($msg);
            }
        };

        if (! Schema::hasTable('groups') || ! Schema::hasTable('group_members')) {
            return ['groups_synced' => 0, 'students_assigned' => 0, 'advisors_count' => 0];
        }

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA temp_store = MEMORY;');
                DB::statement('PRAGMA synchronous = NORMAL;');
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        // 1. Tarik daftar dosen dari API untuk mendapatkan nama lengkap beserta gelar akademik
        $dosenList = $this->apiClient->fetchDosen();

        $dosenByIdPenduduk = [];
        $dosenByNidn = [];

        foreach ($dosenList as $d) {
            $idPenduduk = trim((string) ($d['id_penduduk'] ?? ''));
            $nidn = trim((string) ($d['nidn'] ?? ''));
            $fullName = UnujaProfileService::formatDosenFullName($d);
            if ($fullName === '') {
                continue;
            }

            $canonicalId = $nidn !== '' ? $nidn : $idPenduduk;
            if ($canonicalId === '') {
                continue;
            }

            $info = [
                'identifier'  => $canonicalId,
                'nidn'        => $nidn,
                'id_penduduk' => $idPenduduk,
                'name'        => $fullName,
            ];

            if ($idPenduduk !== '') {
                $dosenByIdPenduduk[$idPenduduk] = $info;
            }
            if ($nidn !== '') {
                $dosenByNidn[$nidn] = $info;
            }

            $this->profileService->saveLocalUserProfile(
                $canonicalId,
                $fullName,
                'Dosen',
                'Fakultas Teknik',
                (string) ($d['prodi'] ?? 'Teknik Informatika')
            );
        }

        // 2. Tarik seluruh mahasiswa aktif Teknik Informatika dari `filter: mahasiswa` (angkatan 2022, 2023, 2024, 2025)
        $studentRowsByNim = [];
        foreach (['2421400', '2321400', '2221400', '2521400'] as $prefix) {
            $rows = $this->apiClient->fetchMahasiswa([
                'search' => $prefix,
            ]);

            foreach ($rows as $row) {
                $nim = trim((string) ($row['nim'] ?? ''));
                if (preg_match('/^\d{10}$/', $nim) === 1) {
                    $studentRowsByNim[$nim] = $row;
                }
            }
        }

        // 3. Kelompokkan mahasiswa berdasarkan Dosen Wali (`id_wali`, `wali`, `nidn`)
        $advisors = [];
        $studentsAssigned = 0;

        foreach ($studentRowsByNim as $nim => $row) {
            if (! empty($row['tahun_keluar'])) {
                continue;
            }

            $studentName = trim((string) ($row['nama'] ?? ''));
            if (UnujaProfileService::isPlaceholderName($studentName, $nim)) {
                $studentName = $this->profileService->getLocalUserName($nim) ?? '';
            }
            if (UnujaProfileService::isPlaceholderName($studentName, $nim)) {
                continue;
            }

            $rawIdWali = trim((string) ($row['id_wali'] ?? ''));
            $rawNidnWali = trim((string) ($row['nidn'] ?? ''));
            $rawNamaWali = trim((string) ($row['wali'] ?? ''));

            if ($rawIdWali === '' && $rawNidnWali === '' && $rawNamaWali === '') {
                continue;
            }

            $matchedDosen = ($rawNidnWali !== '' && isset($dosenByNidn[$rawNidnWali]))
                ? $dosenByNidn[$rawNidnWali]
                : (($rawIdWali !== '' && isset($dosenByIdPenduduk[$rawIdWali])) ? $dosenByIdPenduduk[$rawIdWali] : null);

            $waliIdentifier = $matchedDosen['identifier']
                ?? ($rawNidnWali !== '' ? $rawNidnWali : $rawIdWali);

            if ($waliIdentifier === '') {
                continue;
            }

            $waliName = $matchedDosen['name']
                ?? $this->profileService->getLocalUserName($waliIdentifier)
                ?? $rawNamaWali;

            if (UnujaProfileService::isPlaceholderName($waliName, $waliIdentifier)) {
                continue;
            }

            $prodi = trim((string) ($row['prodi'] ?? 'Teknik Informatika')) ?: 'Teknik Informatika';
            $this->profileService->saveLocalUserProfile($waliIdentifier, $waliName, 'Dosen', 'Fakultas Teknik', $prodi);
            $this->profileService->saveLocalUserProfile($nim, $studentName, 'Mahasiswa', 'Fakultas Teknik', $prodi);

            $angkatan = substr(trim((string) ($row['tahun_masuk'] ?? '')), 0, 4);
            if (preg_match('/^\d{4}$/', $angkatan) !== 1) {
                $angkatan = '20'.substr($nim, 0, 2);
            }

            if (! isset($advisors[$waliIdentifier])) {
                $advisors[$waliIdentifier] = [
                    'identifier'  => $waliIdentifier,
                    'name'        => $waliName,
                    'students'    => [],
                    'by_angkatan' => [],
                ];
            }

            $studentItem = [
                'nim'      => $nim,
                'name'     => $studentName,
                'angkatan' => $angkatan,
            ];

            $advisors[$waliIdentifier]['students'][$nim] = $studentItem;
            $advisors[$waliIdentifier]['by_angkatan'][$angkatan][$nim] = $studentItem;
            $studentsAssigned++;
        }

        // 4. Buat / perbarui Grup Perwalian di tabel `groups` dan anggotanya di `group_members`
        $hasUserNameCol = Schema::hasColumn('group_members', 'user_name');
        $hasTypeCol = Schema::hasColumn('groups', 'type');
        $hasAngkatanCol = Schema::hasColumn('groups', 'angkatan');
        $groupsSynced = 0;

        foreach ($advisors as $waliIdentifier => $advisorData) {
            $waliName = $advisorData['name'];
            $allStudents = $advisorData['students'];
            $byAngkatan = $advisorData['by_angkatan'];

            // Jika satu dosen membimbing >40 mahasiswa lintas angkatan, pecah per angkatan
            $splitByAngkatan = count($allStudents) > 40 && count($byAngkatan) > 1;

            $buckets = [];
            if ($splitByAngkatan) {
                foreach ($byAngkatan as $angk => $studentMap) {
                    $buckets[] = [
                        'name'     => "Perwalian PA — {$waliName} ({$angk})",
                        'angkatan' => (string) $angk,
                        'students' => $studentMap,
                    ];
                }
            } else {
                $singleAngkatan = count($byAngkatan) === 1 ? (string) array_key_first($byAngkatan) : null;
                $buckets[] = [
                    'name'     => "Perwalian PA — {$waliName}",
                    'angkatan' => $singleAngkatan,
                    'students' => $allStudents,
                ];
            }

            foreach ($buckets as $bucket) {
                $groupName = $bucket['name'];
                $groupAngkatan = $bucket['angkatan'];
                $bucketStudents = $bucket['students'];

                $searchAttrs = [
                    'name'                => $groupName,
                    'kode_tahun_akademik' => $semester,
                ];
                if ($hasTypeCol) {
                    $searchAttrs['type'] = 'perwalian';
                }

                $updateAttrs = [
                    'creator_identifier' => $waliIdentifier,
                ];
                if ($hasTypeCol) {
                    $updateAttrs['type'] = 'perwalian';
                }
                if ($hasAngkatanCol) {
                    $updateAttrs['angkatan'] = $groupAngkatan;
                }

                $group = Group::updateOrCreate($searchAttrs, $updateAttrs);

                // Masukkan Dosen Wali sebagai admin grup
                $adminAttrs = ['role' => 'admin'];
                if ($hasUserNameCol) {
                    $adminAttrs['user_name'] = $waliName;
                }
                GroupMember::updateOrCreate(
                    [
                        'group_id'        => $group->id,
                        'user_identifier' => $waliIdentifier,
                    ],
                    $adminAttrs
                );

                // Masukkan seluruh mahasiswa bimbingan sebagai member grup
                foreach ($bucketStudents as $nim => $st) {
                    $memberAttrs = ['role' => 'member'];
                    if ($hasUserNameCol) {
                        $memberAttrs['user_name'] = $st['name'];
                    }
                    GroupMember::updateOrCreate(
                        [
                            'group_id'        => $group->id,
                            'user_identifier' => (string) $nim,
                        ],
                        $memberAttrs
                    );
                }

                // Buat 1 pesan sambutan otomatis dari Dosen Wali jika grup belum memiliki pesan
                if (Schema::hasTable('group_messages') && ! GroupMessage::where('group_id', $group->id)->exists()) {
                    GroupMessage::create([
                        'group_id'          => $group->id,
                        'sender_identifier' => $waliIdentifier,
                        'body'              => "Selamat datang di grup perwalian akademik ({$groupName}). Silakan gunakan ruang ini untuk konsultasi KRS dan bimbingan perkuliahan.",
                    ]);
                }

                $groupsSynced++;
            }
        }

        $log("Berhasil menyinkronkan {$groupsSynced} Grup Perwalian untuk ".count($advisors)." Dosen Wali ({$studentsAssigned} mahasiswa bimbingan).");

        return [
            'groups_synced'     => $groupsSynced,
            'students_assigned' => $studentsAssigned,
            'advisors_count'    => count($advisors),
        ];
    }
}
