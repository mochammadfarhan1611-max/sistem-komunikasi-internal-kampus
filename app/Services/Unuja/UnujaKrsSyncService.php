<?php

namespace App\Services\Unuja;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UnujaKrsSyncService
{
    public function __construct(
        private UnujaApiClient $apiClient,
        private UnujaProfileService $profileService,
        private UnujaPerwalianSyncService $perwalianSyncService,
    ) {}

    /**
     * Bersihkan secara permanen semua NIM salah ketik / tidak valid dan record placeholder
     * dari tabel `group_members`, `users`, `messages`, `group_messages`, serta grup semester usang.
     *
     * @return array{deleted_members: int, deleted_users: int, deleted_groups: int}
     */
    public function cleanupInvalidUsersAndMembers(): array
    {
        $deletedMembers = 0;
        $deletedUsers = 0;
        $deletedGroups = 0;

        $knownInvalidIds = [
            '242140067',
            '242140098',
            '2431400091',
            '0711028801',
            '0715049002',
            '0720088503',
        ];

        $protectedSystemIds = ['unit_baak', 'admin'];

        try {
            // 1. Hapus grup sampah yang memiliki nama "Kelas -" atau dari tahun akademik lama yang tidak valid
            if (Schema::hasTable('groups')) {
                $staleGroupIds = Group::query()
                    ->where('name', 'like', '% - Kelas -%')
                    ->orWhere('kode_tahun_akademik', '!=', '20261')
                    ->pluck('id')
                    ->all();

                if (! empty($staleGroupIds)) {
                    if (Schema::hasTable('group_members')) {
                        GroupMember::whereIn('group_id', $staleGroupIds)->delete();
                    }
                    if (Schema::hasTable('group_messages')) {
                        GroupMessage::whereIn('group_id', $staleGroupIds)->delete();
                    }
                    $deletedGroups = Group::whereIn('id', $staleGroupIds)->delete();
                }
            }

            // 2. Kumpulkan seluruh identifier yang tidak valid dari users dan group_members
            $invalidIds = $knownInvalidIds;

            if (Schema::hasTable('users')) {
                $allUsers = User::all();
                foreach ($allUsers as $u) {
                    $id = trim((string) ($u->identifier ?: $u->username));
                    if ($id === '' || in_array($id, $protectedSystemIds, true)) {
                        continue;
                    }

                    $roleName = strtolower($u->roleName());
                    $name = trim((string) ($u->name ?: $u->nama_lengkap));

                    if ($roleName === 'mahasiswa') {
                        if (preg_match('/^\d{10}$/', $id) !== 1) {
                            $invalidIds[] = $id;

                            continue;
                        }

                        if (UnujaProfileService::isPlaceholderName($name, $id)) {
                            $resolved = $this->profileService->getRealProfileName($id, 'mahasiswa');
                            if (UnujaProfileService::isPlaceholderName($resolved, $id)) {
                                $invalidIds[] = $id;
                            }
                        }
                    } elseif ($roleName === 'dosen') {
                        if (UnujaProfileService::isPlaceholderName($name, $id)) {
                            $resolved = $this->profileService->getRealProfileName($id, 'dosen');
                            if (UnujaProfileService::isPlaceholderName($resolved, $id)) {
                                $invalidIds[] = $id;
                            }
                        }
                    }
                }
            }

            if (Schema::hasTable('group_members')) {
                $allMembers = GroupMember::all();
                foreach ($allMembers as $m) {
                    $id = trim((string) $m->user_identifier);
                    if ($id === '' || in_array($id, $protectedSystemIds, true)) {
                        continue;
                    }

                    $isAdmin = strtolower((string) $m->role) === 'admin' || UnujaProfileService::isDosenIdentifier($id);
                    $name = trim((string) ($m->user_name ?? ''));

                    if (! $isAdmin) {
                        if (preg_match('/^\d{10}$/', $id) !== 1) {
                            $invalidIds[] = $id;

                            continue;
                        }

                        if (UnujaProfileService::isPlaceholderName($name, $id)) {
                            $resolved = $this->profileService->getRealProfileName($id, 'mahasiswa');
                            if (UnujaProfileService::isPlaceholderName($resolved, $id)) {
                                $invalidIds[] = $id;
                            }
                        }
                    } else {
                        if (UnujaProfileService::isPlaceholderName($name, $id)) {
                            $resolved = $this->profileService->getRealProfileName($id, 'dosen');
                            if (UnujaProfileService::isPlaceholderName($resolved, $id)) {
                                $invalidIds[] = $id;
                            }
                        }
                    }
                }
            }

            $invalidIds = array_values(array_unique($invalidIds));

            if (! empty($invalidIds)) {
                if (Schema::hasTable('group_members')) {
                    $deletedMembers += GroupMember::whereIn('user_identifier', $invalidIds)->delete();
                }

                if (Schema::hasTable('users')) {
                    $deletedUsers += User::query()
                        ->whereIn('username', $invalidIds)
                        ->orWhereIn('identifier', $invalidIds)
                        ->orWhereIn('nim', $invalidIds)
                        ->delete();
                }

                if (Schema::hasTable('messages')) {
                    DB::table('messages')
                        ->whereIn('sender_identifier', $invalidIds)
                        ->orWhereIn('receiver_identifier', $invalidIds)
                        ->delete();
                }

                if (Schema::hasTable('group_messages')) {
                    GroupMessage::whereIn('sender_identifier', $invalidIds)
                        ->where('body', 'not like', 'Selamat datang di grup kelas%')
                        ->delete();
                }
            }

            $this->profileService->forgetDirectoryCache();
        } catch (\Throwable $e) {
            Log::warning('Gagal membersihkan data tidak valid: '.$e->getMessage());
        }

        return [
            'deleted_members' => $deletedMembers,
            'deleted_users'   => $deletedUsers,
            'deleted_groups'  => $deletedGroups,
        ];
    }

    /**
     * Tarik seluruh Dosen & Dosen Pengampu Mata Kuliah (`filter: dosen` + `filter: dosen_matakuliah` + `filter: jadwal_matakuliah`)
     * lalu perbarui `groups.creator_identifier`, admin di `group_members`, `users`, dan pesan sambutan di `group_messages`.
     *
     * @return array<string, array{identifier: string, name: string, id_matkul: string, kode_matkul: string, nama_matkul: string, nama_kelas: string, id_kelas: int|null}>
     */
    public function syncAllDosenAndCourseLecturers(string $semester = '20261'): array
    {
        $dosenByNidn = [];
        $dosenByIdPenduduk = [];

        // 1. Tarik daftar master dosen untuk mendapatkan gelar depan & gelar belakang yang lengkap
        $dosenMaster = $this->apiClient->fetchDosen();

        foreach ($dosenMaster as $d) {
            $nidn = trim((string) ($d['nidn'] ?? ''));
            $idPenduduk = trim((string) ($d['id_penduduk'] ?? ''));
            $fullName = UnujaProfileService::formatDosenFullName($d);

            if ($fullName === '') {
                continue;
            }

            $prodi = (string) ($d['prodi'] ?? 'Teknik Informatika');
            $fakultas = (string) ($d['fakultas'] ?? 'Fakultas Teknik');
            if (! str_starts_with(strtolower($fakultas), 'fakultas')) {
                $fakultas = 'Fakultas '.$fakultas;
            }

            $identifier = $nidn !== '' ? $nidn : $idPenduduk;
            if ($identifier === '') {
                continue;
            }

            $info = [
                'identifier'  => $identifier,
                'nidn'        => $nidn,
                'id_penduduk' => $idPenduduk,
                'name'        => $fullName,
                'prodi'       => $prodi,
                'fakultas'    => $fakultas,
            ];

            if ($nidn !== '') {
                $dosenByNidn[$nidn] = $info;
            }
            if ($idPenduduk !== '') {
                $dosenByIdPenduduk[$idPenduduk] = $info;
            }

            // Simpan dosen Prodi Teknik Informatika ke tabel users lokal
            if (($d['id_sms'] ?? '') === UnujaApiClient::TI_ID_SMS || strtolower($prodi) === 'teknik informatika') {
                $this->profileService->saveLocalUserProfile($identifier, $fullName, 'Dosen', $fakultas, $prodi);
            }
        }

        // 2. Tarik seluruh jadwal dosen pengampu mata kuliah di Prodi Teknik Informatika pada semester aktif
        $dosenMkRows = $this->apiClient->fetchDosenMatakuliah([
            'id_sms' => UnujaApiClient::TI_ID_SMS,
            'id_smt' => $semester,
        ]);

        $courseLecturerMap = [];

        foreach ($dosenMkRows as $row) {
            $smt = (string) ($row['id_smt'] ?? $semester);
            if ($smt !== $semester) {
                continue;
            }

            $namaMatkul = trim((string) ($row['nama_matkul'] ?? ''));
            $namaKelas = trim((string) ($row['nama_kelas'] ?? ''));
            if ($namaMatkul === '' || $namaKelas === '') {
                continue;
            }

            $nidn = trim((string) ($row['nidn'] ?? ''));
            $idPenduduk = trim((string) ($row['id_penduduk'] ?? ''));

            $masterInfo = ($nidn !== '' && isset($dosenByNidn[$nidn]))
                ? $dosenByNidn[$nidn]
                : (($idPenduduk !== '' && isset($dosenByIdPenduduk[$idPenduduk])) ? $dosenByIdPenduduk[$idPenduduk] : null);

            $dosenIdentifier = $masterInfo['identifier'] ?? ($nidn !== '' ? $nidn : $idPenduduk);
            $dosenFullName = $masterInfo['name'] ?? UnujaProfileService::formatDosenFullName($row);

            if ($dosenIdentifier === '' || UnujaProfileService::isPlaceholderName($dosenFullName, $dosenIdentifier)) {
                continue;
            }

            $this->profileService->saveLocalUserProfile($dosenIdentifier, $dosenFullName, 'Dosen', 'Fakultas Teknik', 'Teknik Informatika');

            $groupName = 'MK '.$namaMatkul.' - Kelas '.$namaKelas;
            $courseLecturerMap[$groupName] = [
                'identifier'  => $dosenIdentifier,
                'name'        => $dosenFullName,
                'id_matkul'   => (string) ($row['id_matkul'] ?? ''),
                'kode_matkul' => (string) ($row['kode_matkul'] ?? ''),
                'nama_matkul' => $namaMatkul,
                'nama_kelas'  => $namaKelas,
                'id_kelas'    => isset($row['id_kelas']) ? (int) $row['id_kelas'] : null,
            ];
        }

        // 3. Cadangan dari jadwal_matakuliah jika ada kelas yang belum terpetakan di dosen_matakuliah
        $jadwalRows = $this->apiClient->fetchJadwalMatakuliah([
            'id_sms' => UnujaApiClient::TI_ID_SMS,
            'id_smt' => $semester,
        ]);

        foreach ($jadwalRows as $row) {
            $smt = (string) ($row['id_smt'] ?? $semester);
            if ($smt !== $semester) {
                continue;
            }

            $namaMatkul = trim((string) ($row['nama_matkul'] ?? ''));
            $namaKelas = trim((string) ($row['nama_kelas'] ?? ''));
            if ($namaMatkul === '' || $namaKelas === '') {
                continue;
            }

            $groupName = 'MK '.$namaMatkul.' - Kelas '.$namaKelas;
            if (isset($courseLecturerMap[$groupName])) {
                continue;
            }

            $idPenduduk = trim((string) ($row['id_penduduk'] ?? ''));
            $masterInfo = ($idPenduduk !== '' && isset($dosenByIdPenduduk[$idPenduduk])) ? $dosenByIdPenduduk[$idPenduduk] : null;

            $dosenIdentifier = $masterInfo['identifier'] ?? $idPenduduk;
            $dosenFullName = $masterInfo['name'] ?? trim((string) ($row['nama_dosen'] ?? ''));

            if ($dosenIdentifier === '' || UnujaProfileService::isPlaceholderName($dosenFullName, $dosenIdentifier)) {
                continue;
            }

            $this->profileService->saveLocalUserProfile($dosenIdentifier, $dosenFullName, 'Dosen', 'Fakultas Teknik', 'Teknik Informatika');

            $courseLecturerMap[$groupName] = [
                'identifier'  => $dosenIdentifier,
                'name'        => $dosenFullName,
                'id_matkul'   => (string) ($row['id_matkul'] ?? ''),
                'kode_matkul' => (string) ($row['kode_matkul'] ?? ''),
                'nama_matkul' => $namaMatkul,
                'nama_kelas'  => $namaKelas,
                'id_kelas'    => isset($row['id_kelas']) ? (int) $row['id_kelas'] : null,
            ];
        }

        // 4. Perbarui seluruh grup yang ada di tabel `groups` agar menggunakan Dosen Pengampu Asli
        if (Schema::hasTable('groups')) {
            $hasUserNameCol = Schema::hasColumn('group_members', 'user_name');
            $groups = Group::where('kode_tahun_akademik', $semester)->get();

            foreach ($groups as $group) {
                $lecturer = $courseLecturerMap[$group->name] ?? null;
                if (! $lecturer) {
                    continue;
                }

                $dosenId = $lecturer['identifier'];
                $dosenName = $lecturer['name'];

                if ($group->creator_identifier !== $dosenId) {
                    $group->creator_identifier = $dosenId;
                    $group->save();
                }

                if (Schema::hasTable('group_members')) {
                    GroupMember::where('group_id', $group->id)
                        ->where('role', 'admin')
                        ->where('user_identifier', '!=', $dosenId)
                        ->delete();

                    $adminAttrs = ['role' => 'admin'];
                    if ($hasUserNameCol) {
                        $adminAttrs['user_name'] = $dosenName;
                    }

                    GroupMember::updateOrCreate(
                        [
                            'group_id'        => $group->id,
                            'user_identifier' => $dosenId,
                        ],
                        $adminAttrs
                    );
                }

                if (Schema::hasTable('group_messages')) {
                    GroupMessage::where('group_id', $group->id)
                        ->where('body', 'like', 'Selamat datang di grup kelas%')
                        ->update([
                            'sender_identifier' => $dosenId,
                        ]);
                }
            }
        }

        return $courseLecturerMap;
    }

    /**
     * Tarik 100% seluruh anggota kelas dari API Kampus untuk semester aktif (20261)
     * menggunakan kombinasi:
     * 1) `filter: mahasiswa_matakuliah` per `id_matkul` (dengan dukungan pagination penuh)
     * 2) Batch Cohort Discovery (`filter: mahasiswa` + `Http::pool()` per NIM mahasiswa seangkatan)
     *
     * @return array{groups_synced: int, total_groups: int, perwalian_groups_synced: int, students_synced: int, machine_learning_c_count: int}
     */
    public function syncFullCampusKrsSemester(string $semester = '20261', ?callable $logger = null): array
    {
        $log = function (string $msg) use ($logger): void {
            if ($logger !== null) {
                $logger($msg);
            }
        };

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA temp_store = MEMORY;');
                DB::statement('PRAGMA synchronous = NORMAL;');
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        // LANGKAH 1: Bersihkan NIM salah ketik & data sampah terlebih dahulu
        $log('Langkah 1/4: Membersihkan NIM typo/tidak valid & grup usang...');
        $this->cleanupInvalidUsersAndMembers();

        // LANGKAH 2: Tarik seluruh Dosen Pengampu Asli dari API (`dosen` + `dosen_matakuliah` + `jadwal_matakuliah`)
        $log('Langkah 2/4: Menarik data Dosen Pengampu Asli beserta gelar dari API Kampus...');
        $courseLecturerMap = $this->syncAllDosenAndCourseLecturers($semester);
        $log('Berhasil memetakan '.count($courseLecturerMap).' kelas mata kuliah dengan Dosen Pengampu Asli.');

        $hasUserNameCol = Schema::hasColumn('group_members', 'user_name');
        $studentsSynced = [];

        // Pastikan 8 mata kuliah utama mahasiswa utama (2421400177) dan seluruh grup yang sudah ada di DB terdaftar
        $farhanKrs = $this->getKrsData('2421400177', 'mahasiswa');
        foreach ($farhanKrs as $item) {
            $gName = (string) $item['group_name'];
            $lec = $courseLecturerMap[$gName] ?? null;
            $dosenId = $lec['identifier'] ?? (string) ($item['dosen_identifier'] ?? '0701098506');
            $dosenName = $lec['name'] ?? ($this->profileService->getLocalUserName($dosenId) ?? 'ABU THOLIB, M.Kom.');

            $group = Group::firstOrCreate(
                ['name' => $gName, 'kode_tahun_akademik' => $semester],
                ['creator_identifier' => $dosenId]
            );

            if ($group->creator_identifier !== $dosenId) {
                $group->update(['creator_identifier' => $dosenId]);
            }

            $adminAttrs = ['role' => 'admin'];
            if ($hasUserNameCol) {
                $adminAttrs['user_name'] = $dosenName;
            }
            GroupMember::updateOrCreate(
                ['group_id' => $group->id, 'user_identifier' => $dosenId],
                $adminAttrs
            );
        }

        // Kumpulkan semua id_matkul unik dari courseLecturerMap yang memiliki grup di DB
        $existingGroupNames = Group::where('kode_tahun_akademik', $semester)->pluck('name')->flip()->all();
        $idMatkulSet = [];
        $tiIdKelasSet = [];

        foreach ($courseLecturerMap as $gName => $info) {
            if (! empty($info['id_kelas'])) {
                $tiIdKelasSet[(int) $info['id_kelas']] = $gName;
            }
            if (isset($existingGroupNames[$gName]) && ! empty($info['id_matkul'])) {
                $idMatkulSet[$info['id_matkul']] = $info['nama_matkul'];
            }
        }

        // LANGKAH 3A: Tarik peserta kelas per `id_matkul` menggunakan `filter: mahasiswa_matakuliah`
        $log('Langkah 3A/4: Menarik peserta kelas per mata kuliah ('.count($idMatkulSet).' mata kuliah aktif)...');
        foreach ($idMatkulSet as $idMatkul => $namaMatkul) {
            $rows = $this->apiClient->fetchMahasiswaMatakuliah([
                'id_matkul' => $idMatkul,
                'id_smt'    => $semester,
            ]);

            foreach ($rows as $row) {
                $smt = (string) ($row['id_smt'] ?? '');
                if ($smt !== $semester) {
                    continue;
                }

                $nim = trim((string) ($row['nim'] ?? ''));
                $nama = trim((string) ($row['nama'] ?? $row['nama_mahasiswa'] ?? ''));
                $namaMk = trim((string) ($row['nama_matkul'] ?? $namaMatkul));
                $namaKelas = trim((string) ($row['nama_kelas'] ?? ''));
                $idKelas = isset($row['id_kelas']) ? (int) $row['id_kelas'] : 0;
                $kurikulum = strtoupper(trim((string) ($row['nama_kurikulum'] ?? '')));

                if (preg_match('/^\d{10}$/', $nim) !== 1 || UnujaProfileService::isPlaceholderName($nama, $nim) || $namaKelas === '' || $namaKelas === '-') {
                    continue;
                }

                $isTiClass = ($idKelas > 0 && isset($tiIdKelasSet[$idKelas]))
                    || str_starts_with($kurikulum, 'IF')
                    || substr($nim, 2, 4) === '2140';

                if (! $isTiClass) {
                    continue;
                }

                $groupName = 'MK '.$namaMk.' - Kelas '.$namaKelas;
                $lec = $courseLecturerMap[$groupName] ?? null;
                $dosenId = $lec['identifier'] ?? null;
                $dosenName = $lec['name'] ?? null;

                $group = Group::where('name', $groupName)->where('kode_tahun_akademik', $semester)->first();
                if (! $group) {
                    if (! $dosenId) {
                        continue;
                    }
                    $group = Group::create([
                        'name'                => $groupName,
                        'kode_tahun_akademik' => $semester,
                        'creator_identifier'  => $dosenId,
                    ]);
                }

                if ($dosenId && $dosenName) {
                    $adminAttrs = ['role' => 'admin'];
                    if ($hasUserNameCol) {
                        $adminAttrs['user_name'] = $dosenName;
                    }
                    GroupMember::updateOrCreate(
                        ['group_id' => $group->id, 'user_identifier' => $dosenId],
                        $adminAttrs
                    );
                }

                $this->profileService->saveLocalUserProfile($nim, $nama, 'Mahasiswa', 'Fakultas Teknik', 'Teknik Informatika');

                $memberAttrs = ['role' => 'member'];
                if ($hasUserNameCol) {
                    $memberAttrs['user_name'] = $nama;
                }
                GroupMember::updateOrCreate(
                    ['group_id' => $group->id, 'user_identifier' => $nim],
                    $memberAttrs
                );

                $studentsSynced[$nim] = $nama;
            }
        }

        // LANGKAH 3B: Cohort Discovery (angkatan 2024 & 2023 + scan paralel KRS 20261)
        $log('Langkah 3B/4: Menjalankan Cohort Discovery mahasiswa Teknik Informatika (angkatan 2024 & 2023)...');
        $cohortNims = [];

        foreach (['2421400', '2321400'] as $prefix) {
            $mhsList = $this->apiClient->fetchMahasiswa([
                'search' => $prefix,
            ]);

            foreach ($mhsList as $mRow) {
                $nim = trim((string) ($mRow['nim'] ?? ''));
                $nama = trim((string) ($mRow['nama'] ?? ''));
                if (preg_match('/^\d{10}$/', $nim) !== 1 || UnujaProfileService::isPlaceholderName($nama, $nim)) {
                    continue;
                }

                $this->profileService->saveLocalUserProfile($nim, $nama, 'Mahasiswa', 'Fakultas Teknik', 'Teknik Informatika');
                $studentsSynced[$nim] = $nama;

                if (str_starts_with($nim, '2421400')) {
                    $cohortNims[$nim] = $nama;
                }
            }
        }

        $dataUrl = $this->apiClient->getDataUrl();

        if ($dataUrl && ! empty($cohortNims)) {
            $nimChunks = array_chunk(array_keys($cohortNims), 25);
            $log('Memindai KRS semester '.$semester.' untuk '.count($cohortNims).' mahasiswa angkatan 2024 secara paralel ('.count($nimChunks).' batch)...');

            foreach ($nimChunks as $nimBatch) {
                $responses = $this->apiClient->fetchMahasiswaMatakuliahPool($nimBatch, $dataUrl, 15);

                foreach ($nimBatch as $nim) {
                    $res = $responses[(string) $nim] ?? null;
                    if (! $res || ! ($res instanceof Response) || ! $res->successful()) {
                        continue;
                    }

                    $krsRows = $res->json()['data'] ?? [];
                    if (! is_array($krsRows) || empty($krsRows)) {
                        continue;
                    }

                    $mhsName = $cohortNims[$nim] ?? trim((string) ($krsRows[0]['nama'] ?? ''));
                    if (UnujaProfileService::isPlaceholderName($mhsName, (string) $nim)) {
                        continue;
                    }

                    foreach ($krsRows as $kRow) {
                        if (! is_array($kRow)) {
                            continue;
                        }

                        $smt = (string) ($kRow['id_smt'] ?? '');
                        if ($smt !== $semester) {
                            continue;
                        }

                        $namaMk = trim((string) ($kRow['nama_matkul'] ?? ''));
                        $namaKelas = trim((string) ($kRow['nama_kelas'] ?? ''));
                        if ($namaMk === '' || $namaKelas === '' || $namaKelas === '-') {
                            continue;
                        }

                        $groupName = 'MK '.$namaMk.' - Kelas '.$namaKelas;
                        $lec = $courseLecturerMap[$groupName] ?? null;
                        $dosenId = $lec['identifier'] ?? null;
                        $dosenName = $lec['name'] ?? null;

                        $group = Group::where('name', $groupName)->where('kode_tahun_akademik', $semester)->first();
                        if (! $group) {
                            if (! $dosenId) {
                                continue;
                            }
                            $group = Group::create([
                                'name'                => $groupName,
                                'kode_tahun_akademik' => $semester,
                                'creator_identifier'  => $dosenId,
                            ]);
                        }

                        if ($dosenId && $dosenName) {
                            $adminAttrs = ['role' => 'admin'];
                            if ($hasUserNameCol) {
                                $adminAttrs['user_name'] = $dosenName;
                            }
                            GroupMember::updateOrCreate(
                                ['group_id' => $group->id, 'user_identifier' => $dosenId],
                                $adminAttrs
                            );
                        }

                        $memberAttrs = ['role' => 'member'];
                        if ($hasUserNameCol) {
                            $memberAttrs['user_name'] = $mhsName;
                        }
                        GroupMember::updateOrCreate(
                            ['group_id' => $group->id, 'user_identifier' => (string) $nim],
                            $memberAttrs
                        );
                    }
                }
            }
        }

        // LANGKAH 4: Finalisasi Dosen Pengampu, pesan sambutan grup, dan kebersihan database
        $log('Langkah 4/5: Finalisasi Dosen Pengampu, pesan sambutan grup, dan validasi kebersihan database...');
        $this->syncAllDosenAndCourseLecturers($semester);
        $this->cleanupInvalidUsersAndMembers();

        if (Schema::hasTable('group_messages')) {
            foreach (Group::where('kode_tahun_akademik', $semester)->where('type', 'kelas')->get() as $g) {
                if (! GroupMessage::where('group_id', $g->id)->exists()) {
                    GroupMessage::create([
                        'group_id'          => $g->id,
                        'sender_identifier' => $g->creator_identifier,
                        'body'              => "Selamat datang di grup kelas {$g->name} (Semester Ganjil 2026/2027). Silakan gunakan ruang ini untuk diskusi dan koordinasi perkuliahan.",
                    ]);
                }
            }
        }

        // LANGKAH 5: Sinkronisasi Grup Perwalian / Bimbingan Akademik (Dosen Wali) dari API Kampus
        $log('Langkah 5/5: Menyinkronkan Grup Perwalian / Bimbingan Akademik (Dosen Wali) dari API Kampus...');
        $perwalianSummary = $this->perwalianSyncService->syncPerwalianGroupsFromApi($semester, $logger);

        // Segarkan cache direktori dari database lokal yang sudah bersih
        $this->profileService->refreshDirectoryCache();

        $mlGroup = Group::where('name', 'MK Machine Learning - Kelas C')
            ->where('kode_tahun_akademik', $semester)
            ->first();
        $mlCount = $mlGroup ? GroupMember::where('group_id', $mlGroup->id)->count() : 0;

        return [
            'groups_synced'            => Group::where('kode_tahun_akademik', $semester)->count(),
            'total_groups'             => Group::where('kode_tahun_akademik', $semester)->count(),
            'perwalian_groups_synced'  => $perwalianSummary['groups_synced'] ?? 0,
            'students_synced'          => count($studentsSynced),
            'machine_learning_c_count' => $mlCount,
        ];
    }

    /**
     * Sinkronisasi & resolusi otomatis Nama Asli seluruh anggota kelas dari API Kampus
     * agar tidak ada lagi anggota yang berstatus "Mahasiswa Peserta Kelas" atau "Mahasiswa (...)".
     */
    public function syncClassmatesFromApi(?Group $group = null): void
    {
        if (! Schema::hasTable('group_members')) {
            return;
        }

        // Jika dipanggil tanpa argumen (misal via CLI sync:krs), jalankan sinkronisasi penuh
        if ($group === null) {
            $this->syncFullCampusKrsSemester('20261');

            return;
        }

        // Cek apakah masih ada nama placeholder atau jumlah anggota masih sedikit (< 12 orang)
        $members = GroupMember::where('group_id', $group->id)->get();
        $hasPlaceholder = false;

        foreach ($members as $member) {
            $id = trim((string) $member->user_identifier);
            $name = trim((string) ($member->user_name ?? ''));
            if (UnujaProfileService::isPlaceholderName($name, $id)) {
                $hasPlaceholder = true;
                break;
            }
        }

        if (! $hasPlaceholder && $members->count() >= 12) {
            return;
        }

        foreach ($members as $member) {
            $identifier = trim((string) $member->user_identifier);
            if ($identifier === '') {
                continue;
            }

            $roleType = (strtolower((string) $member->role) === 'admin' || UnujaProfileService::isDosenIdentifier($identifier)) ? 'dosen' : 'mahasiswa';
            $currentMemberName = trim((string) ($member->user_name ?? ''));
            $localName = $this->profileService->getLocalUserName($identifier);

            if ($localName !== null && ! UnujaProfileService::isPlaceholderName($localName, $identifier)) {
                $realName = $localName;
            } elseif (! UnujaProfileService::isPlaceholderName($currentMemberName, $identifier)) {
                $realName = $currentMemberName;
            } else {
                $realName = $this->profileService->getRealProfileName($identifier, $roleType);
            }

            if ($roleType === 'mahasiswa' && (preg_match('/^\d{10}$/', $identifier) !== 1 || UnujaProfileService::isPlaceholderName($realName, $identifier))) {
                GroupMember::where('id', $member->id)->delete();

                continue;
            }

            if (! UnujaProfileService::isPlaceholderName($realName, $identifier)) {
                $this->profileService->saveLocalUserProfile(
                    $identifier,
                    $realName,
                    $roleType === 'dosen' ? 'Dosen' : 'Mahasiswa',
                    'Fakultas Teknik',
                    'Teknik Informatika'
                );
            }
        }
    }

    /**
     * Sinkronisasi direktori Mahasiswa & Dosen dari API Portal SSO ke Database Lokal.
     */
    public function syncDirectoryFromPortal(): void
    {
        $this->profileService->upsertDirectoryContacts($this->profileService->defaultSeedContacts());
        $this->syncClassmatesFromApi();
    }

    /**
     * Tarik jadwal KRS dari API Kampus UNUJA dan otomatis simpan nama asli mahasiswa dari payload KRS ke DB lokal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKrsData(string $identifier, string $role): array
    {
        try {
            $identifier = trim($identifier);
            $role = strtolower(trim($role));

            // STEP 1: MINTA IZIN (AUTHORIZE)
            $dataUrl = $this->apiClient->getDataUrl();
            if (! $dataUrl) {
                return [];
            }

            // STEP 2: TARIK DATA DATABASE KAMPUS (REAL)
            $searchPayload = [
                'pagination' => 'off',
            ];

            if ($role === 'mahasiswa') {
                $searchPayload['filter'] = 'mahasiswa_matakuliah';
                $searchPayload['nim'] = $identifier;
            } else {
                // LOGIKA DOSEN: 2-STEP FETCH (Tanpa mengekspos NIK)
                $searchDosen = $this->apiClient->postDataRequest([
                    'filter'     => 'dosen',
                    'pagination' => 'off',
                ], 10, $dataUrl);

                $dosenData = $searchDosen?->json();
                $nikRahasia = null;

                foreach (($dosenData['data'] ?? []) as $dosenItem) {
                    if (is_array($dosenItem) && isset($dosenItem['nidn']) && (string) $dosenItem['nidn'] === $identifier) {
                        $nikRahasia = $dosenItem['id_penduduk'] ?? null;
                        break;
                    }
                }

                if (! $nikRahasia) {
                    return [];
                }

                $searchPayload['filter'] = 'dosen_matakuliah';
                $searchPayload['id_penduduk'] = $nikRahasia;
            }

            $response = $this->apiClient->postDataRequest($searchPayload, 10, $dataUrl);
            if (! $response) {
                return [];
            }

            $responseData = $response->json();

            // STEP 3: MAPPING UNTUK DISIMPAN KE DB LOKAL
            if ($response->successful() && ! empty($responseData['data']) && is_array($responseData['data'])) {
                $rawItems = $responseData['data'];

                // Ekstrak dan simpan nama asli mahasiswa langsung dari payload KRS API Kampus
                foreach ($rawItems as $rawRow) {
                    if (! is_array($rawRow)) {
                        continue;
                    }
                    $rowNim = trim((string) ($rawRow['nim'] ?? $rawRow['mahasiswa_id'] ?? ($role === 'mahasiswa' ? $identifier : '')));
                    $rowNama = trim((string) ($rawRow['nama'] ?? $rawRow['nama_mahasiswa'] ?? $rawRow['nama_penduduk'] ?? ''));

                    if ($rowNim !== '' && ! UnujaProfileService::isPlaceholderName($rowNama, $rowNim)) {
                        $this->profileService->saveLocalUserProfile($rowNim, $rowNama, 'Mahasiswa', 'Fakultas Teknik', 'Teknik Informatika');
                        break;
                    }
                }

                // Hanya ambil mata kuliah yang memiliki kelas valid (bukan "-") pada semester aktif (20261)
                $activeSemesterItems = array_values(array_filter($rawItems, function ($row): bool {
                    if (! is_array($row)) {
                        return false;
                    }
                    $smt = (string) ($row['id_smt'] ?? $row['tahun_akademik'] ?? $row['semester'] ?? '');
                    $kelas = trim((string) ($row['nama_kelas'] ?? $row['kelas'] ?? ''));

                    return $smt === '20261' && $kelas !== '' && $kelas !== '-';
                }));

                $formattedData = [];

                foreach ($activeSemesterItems as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $namaMk    = trim((string) ($item['nama_matkul'] ?? $item['nama_mk'] ?? $item['mata_kuliah'] ?? $item['mk'] ?? 'Mata Kuliah'));
                    $kelas     = trim((string) ($item['nama_kelas'] ?? $item['kelas'] ?? '-'));
                    $groupName = 'MK '.$namaMk.' - Kelas '.$kelas;
                    $tahun     = (string) ($item['id_smt'] ?? $item['tahun_akademik'] ?? $item['semester'] ?? '20261');
                    $jadwal    = (string) ($item['nm_smt'] ?? 'Semester Ganjil 2026/2027');

                    $existingGroup = Schema::hasTable('groups')
                        ? Group::where('name', $groupName)->where('kode_tahun_akademik', $tahun)->first()
                        : null;

                    $dosenId = trim((string) ($item['nidn'] ?? $item['dosen_id'] ?? ($role === 'dosen' ? $identifier : ($existingGroup?->creator_identifier ?? ''))));
                    if ($dosenId === '' || $dosenId === '0711028801') {
                        $dosenId = $existingGroup?->creator_identifier ?: '0701098506';
                    }

                    $mhsId   = $item['nim'] ?? $item['mahasiswa_id'] ?? ($role === 'mahasiswa' ? $identifier : null);
                    $mhsNama = trim((string) ($item['nama'] ?? $item['nama_mahasiswa'] ?? $item['nama_penduduk'] ?? ''));

                    $formattedData[] = [
                        'id_kelas'             => $item['id_kelas'] ?? null,
                        'id_matkul'            => (string) ($item['id_matkul'] ?? ''),
                        'kode_mk'              => (string) ($item['kode_matkul'] ?? $item['kode_mk'] ?? ''),
                        'nama_mk'              => $namaMk,
                        'kelas'                => $kelas,
                        'group_name'           => $groupName,
                        'tahun_akademik'       => $tahun,
                        'jadwal'               => $jadwal,
                        'dosen_identifier'     => $dosenId,
                        'mahasiswa_identifier' => (string) $mhsId,
                        'mahasiswa_name'       => $mhsNama,
                    ];
                }

                return $formattedData;
            }

            return [];
        } catch (\Throwable $e) {
            Log::error('System Error API: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Alias fallback data KRS apabila tabel groups di DB lokal masih benar-benar kosong dan API offline.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKrsFallbackData(string $identifier, string $role): array
    {
        $realName = $this->profileService->getLocalUserName($identifier) ?? ($role === 'dosen' ? 'ABU THOLIB, M.Kom.' : 'MOCHAMMAD FARHAN');

        return [
            [
                'kode_mk'              => 'MKU321419',
                'group_name'           => 'MK Microservices Architecture - Kelas B',
                'tahun_akademik'       => '20261',
                'jadwal'               => '2026/2027 Ganjil',
                'dosen_identifier'     => '0728069505',
                'mahasiswa_identifier' => $role === 'mahasiswa' ? $identifier : '2421400177',
                'mahasiswa_name'       => $realName,
            ],
            [
                'kode_mk'              => 'MKU321415',
                'group_name'           => 'MK Machine Learning - Kelas C',
                'tahun_akademik'       => '20261',
                'jadwal'               => '2026/2027 Ganjil',
                'dosen_identifier'     => '0701098506',
                'mahasiswa_identifier' => $role === 'mahasiswa' ? $identifier : '2421400177',
                'mahasiswa_name'       => $realName,
            ],
            [
                'kode_mk'              => 'MKU321414',
                'group_name'           => 'MK Mobile Computing - Kelas E',
                'tahun_akademik'       => '20261',
                'jadwal'               => '2026/2027 Ganjil',
                'dosen_identifier'     => '0704049402',
                'mahasiswa_identifier' => $role === 'mahasiswa' ? $identifier : '2421400177',
                'mahasiswa_name'       => $realName,
            ],
        ];
    }

    /**
     * Sinkronisasi otomatis Direktori Lokal & Grup Kelas KRS untuk pengguna yang sedang aktif.
     */
    public function syncUserKrsGroups(User $user): int
    {
        if (! Schema::hasTable('groups') || ! Schema::hasTable('group_members')) {
            return 0;
        }

        $userId = trim((string) $user->id);
        $role = strtolower($user->roleName());

        // Pastikan akun dasar & user saat ini tersimpan di DB lokal dengan Anti-Overwrite Guard
        $this->profileService->upsertDirectoryContacts($this->profileService->defaultSeedContacts());

        $userName = trim((string) ($user->nama_lengkap ?: $user->name));
        if (! UnujaProfileService::isPlaceholderName($userName, $userId)) {
            $this->profileService->saveLocalUserProfile(
                $userId,
                $userName,
                $user->roleName(),
                $user->affiliationLabel(),
                (string) ($user->prodi?->nama_prodi ?? 'Teknik Informatika')
            );
        }

        if (! in_array($role, ['mahasiswa', 'dosen'], true)) {
            session(['krs_synced' => true]);

            return 0;
        }

        // Pastikan Grup Perwalian (Dosen Wali) juga sudah tersinkronisasi di database lokal
        if (Schema::hasColumn('groups', 'type') && ! Group::where('type', 'perwalian')->exists()) {
            $this->perwalianSyncService->syncPerwalianGroupsFromApi('20261');
        }

        // Jika pengguna sudah memiliki grup KRS di database lokal, tidak perlu memanggil ulang API setiap request
        $existingMemberCount = GroupMember::where('user_identifier', $userId)->count();
        if ($existingMemberCount >= 3) {
            session(['krs_synced' => true]);

            return $existingMemberCount;
        }

        $krsClasses = $this->getKrsData($userId, $role);

        if (empty($krsClasses)) {
            session(['krs_synced' => true]);

            return $existingMemberCount;
        }

        $syncedCount = 0;

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA temp_store = MEMORY;');
                DB::statement('PRAGMA synchronous = NORMAL;');
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        $hasUserNameCol = Schema::hasColumn('group_members', 'user_name');

        foreach ($krsClasses as $item) {
            try {
                $groupName = (string) ($item['group_name'] ?? 'Kelas Mata Kuliah');
                $tahunAkademik = (string) ($item['tahun_akademik'] ?? '20261');
                $jadwal = (string) ($item['jadwal'] ?? 'Semester Ganjil 2026/2027');

                $existingGroup = Group::where('name', $groupName)->where('kode_tahun_akademik', $tahunAkademik)->first();
                $dosenId = trim((string) ($existingGroup?->creator_identifier ?: ($item['dosen_identifier'] ?? '0701098506')));
                $dosenName = $this->profileService->getLocalUserName($dosenId) ?? $this->profileService->getRealProfileName($dosenId, 'dosen');
                if (! UnujaProfileService::isPlaceholderName($dosenName, $dosenId)) {
                    $this->profileService->saveLocalUserProfile($dosenId, $dosenName, 'Dosen');
                }

                // 1. Buat atau ambil grup mata kuliah di tabel groups
                $group = $existingGroup ?: Group::create([
                    'name'                => $groupName,
                    'kode_tahun_akademik' => $tahunAkademik,
                    'creator_identifier'  => $dosenId,
                ]);

                // 2. Masukkan Dosen Pengampu sebagai admin grup
                if (! UnujaProfileService::isPlaceholderName($dosenName, $dosenId)) {
                    $adminAttrs = ['role' => 'admin'];
                    if ($hasUserNameCol) {
                        $adminAttrs['user_name'] = $dosenName;
                    }
                    GroupMember::updateOrCreate(
                        [
                            'group_id'        => $group->id,
                            'user_identifier' => $dosenId,
                        ],
                        $adminAttrs
                    );
                }

                // 3. Masukkan Mahasiswa peserta kelas beserta nama aslinya
                $mhsNim = trim((string) ($item['mahasiswa_identifier'] ?? ($role === 'mahasiswa' ? $userId : '')));
                $mhsNama = trim((string) ($item['mahasiswa_name'] ?? ''));

                if ($mhsNim !== '' && preg_match('/^\d{10}$/', $mhsNim) === 1) {
                    if (UnujaProfileService::isPlaceholderName($mhsNama, $mhsNim)) {
                        $mhsNama = $this->profileService->getRealProfileName($mhsNim, 'mahasiswa');
                    } else {
                        $this->profileService->saveLocalUserProfile($mhsNim, $mhsNama, 'Mahasiswa');
                    }

                    if (! UnujaProfileService::isPlaceholderName($mhsNama, $mhsNim)) {
                        $memberAttrs = ['role' => 'member'];
                        if ($hasUserNameCol) {
                            $memberAttrs['user_name'] = $mhsNama;
                        }

                        GroupMember::updateOrCreate(
                            [
                                'group_id'        => $group->id,
                                'user_identifier' => $mhsNim,
                            ],
                            $memberAttrs
                        );
                    }
                }

                // 4. Buat pesan sambutan otomatis dari Dosen Pengampu jika grup belum memiliki pesan
                if (Schema::hasTable('group_messages') && ! GroupMessage::where('group_id', $group->id)->exists()) {
                    GroupMessage::create([
                        'group_id'          => $group->id,
                        'sender_identifier' => $dosenId,
                        'body'              => "Selamat datang di grup kelas {$groupName} ({$jadwal}). Silakan gunakan ruang ini untuk diskusi dan koordinasi perkuliahan.",
                    ]);
                }

                $syncedCount++;
            } catch (\Throwable $e) {
                Log::error('Gagal menyinkronkan Grup Kelas KRS: '.$e->getMessage());
            }
        }

        session(['krs_synced' => true]);

        return $syncedCount;
    }
}
