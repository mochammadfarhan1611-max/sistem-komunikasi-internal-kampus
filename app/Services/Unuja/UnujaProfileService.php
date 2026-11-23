<?php

namespace App\Services\Unuja;

use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UnujaProfileService
{
    public const DIRECTORY_CACHE_KEY = 'skik:directory:contacts';

    public function __construct(
        private UnujaApiClient $apiClient,
    ) {}

    /**
     * Periksa apakah sebuah nama merupakan teks placeholder / dummy yang TIDAK BOLEH menimpa nama asli.
     */
    public static function isPlaceholderName(?string $name, ?string $identifier = null): bool
    {
        $clean = trim((string) $name);
        if ($clean === '') {
            return true;
        }

        // Nama yang hanya berisi angka/NIM dianggap sebagai placeholder
        if (preg_match('/^\d+$/', $clean) === 1) {
            return true;
        }

        $lower = strtolower($clean);
        $idLower = strtolower(trim((string) $identifier));

        if ($idLower !== '' && $lower === $idLower) {
            return true;
        }

        $placeholderPrefixes = [
            'mahasiswa (',
            'dosen (',
            'sivitas (',
            'mahasiswa peserta kelas',
            'dosen pembimbing',
            'rekan mahasiswa',
            'mahasiswa unuja',
            'dosen unuja',
            'sivitas unuja',
            'dosen pengampu',
            'peserta kelas',
        ];

        foreach ($placeholderPrefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        if (preg_match('/^(mahasiswa|dosen|sivitas)\s+\d+$/i', $clean) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Periksa apakah sebuah identifier merupakan identifier Dosen (NIDN atau id_penduduk API UNUJA).
     */
    public static function isDosenIdentifier(string $identifier): bool
    {
        $clean = strtolower(trim($identifier));

        return str_starts_with($clean, '07')
            || str_starts_with($clean, '00')
            || str_starts_with($clean, 'aaaa');
    }

    /**
     * Format nama lengkap dosen beserta gelar depan dan gelar belakang dari payload API UNUJA.
     *
     * @param  array<string, mixed>  $row
     */
    public static function formatDosenFullName(array $row): string
    {
        $baseName = trim((string) ($row['nama_penduduk'] ?? $row['nama_dosen'] ?? $row['nama'] ?? ''));
        if ($baseName === '') {
            return '';
        }

        $gelarDepan = trim((string) ($row['gelar_depan'] ?? ''));
        $gelarBelakang = trim((string) ($row['gelar_belakang'] ?? ''));

        $full = $baseName;
        if ($gelarDepan !== '' && ! str_starts_with(strtolower($full), strtolower($gelarDepan))) {
            $full = $gelarDepan.' '.$full;
        }
        if ($gelarBelakang !== '' && ! str_ends_with(strtolower($full), strtolower($gelarBelakang))) {
            $full .= ', '.$gelarBelakang;
        }

        return trim($full);
    }

    /**
     * Daftar kontak resmi bawaan (4 Unit Layanan Kampus, Admin, dan pengguna utama).
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaultSeedContacts(): array
    {
        return [
            '2421400177' => [
                'identifier'     => '2421400177',
                'username'       => '2421400177',
                'nim'            => '2421400177',
                'name'           => 'MOCHAMMAD FARHAN',
                'nama_lengkap'   => 'MOCHAMMAD FARHAN',
                'role'           => 'Mahasiswa',
                'prodi'          => 'Teknik Informatika',
                'fakultas'       => 'Fakultas Teknik',
                'unit'           => 'Fakultas Teknik',
                'tahun_angkatan' => 2024,
                'status_akun'    => 'Aktif',
            ],
            'unit_baak' => [
                'identifier'        => 'unit_baak',
                'username'          => 'unit_baak',
                'kode_unit'         => 'BAAK',
                'name'              => 'Layanan Akademik / BAAK',
                'nama_lengkap'      => 'Layanan Akademik / BAAK',
                'role'              => 'Staff_Unit',
                'prodi'             => null,
                'fakultas'          => null,
                'unit'              => 'Biro Administrasi Akademik & Kemahasiswaan',
                'deskripsi_layanan' => 'Melayani administrasi akademik, KRS, KHS, dan layanan kemahasiswaan',
                'status_akun'       => 'Aktif',
            ],
            'unit_bau' => [
                'identifier'        => 'unit_bau',
                'username'          => 'unit_bau',
                'kode_unit'         => 'BAU',
                'name'              => 'Layanan Keuangan / BAU',
                'nama_lengkap'      => 'Layanan Keuangan / BAU',
                'role'              => 'Staff_Unit',
                'prodi'             => null,
                'fakultas'          => null,
                'unit'              => 'Biro Administrasi Umum & Keuangan',
                'deskripsi_layanan' => 'Melayani pembayaran UKT, validasi keuangan, dan administrasi umum',
                'status_akun'       => 'Aktif',
            ],
            'unit_ft' => [
                'identifier'        => 'unit_ft',
                'username'          => 'unit_ft',
                'kode_unit'         => 'TU-FT',
                'name'              => 'Tata Usaha Fakultas Teknik',
                'nama_lengkap'      => 'Tata Usaha Fakultas Teknik',
                'role'              => 'Staff_Unit',
                'prodi'             => null,
                'fakultas'          => 'Fakultas Teknik',
                'unit'              => 'Tata Usaha Fakultas Teknik',
                'deskripsi_layanan' => 'Melayani surat pengantar PKL, skripsi, dan administrasi Fakultas Teknik',
                'status_akun'       => 'Aktif',
            ],
            'unit_pdsi' => [
                'identifier'        => 'unit_pdsi',
                'username'          => 'unit_pdsi',
                'kode_unit'         => 'PDSI',
                'name'              => 'Pusat Data & Sistem Informasi',
                'nama_lengkap'      => 'Pusat Data & Sistem Informasi',
                'role'              => 'Staff_Unit',
                'prodi'             => null,
                'fakultas'          => null,
                'unit'              => 'Pusat Data & Sistem Informasi (PDSI)',
                'deskripsi_layanan' => 'Melayani akun SSO, jaringan kampus, dan sistem informasi akademik',
                'status_akun'       => 'Aktif',
            ],
            'admin' => [
                'identifier'   => 'admin',
                'username'     => 'admin',
                'name'         => 'Administrator SKIK',
                'nama_lengkap' => 'Administrator SKIK',
                'role'         => 'Admin',
                'prodi'        => null,
                'fakultas'     => null,
                'unit'         => 'PUSKOM / PDSI UNUJA',
                'status_akun'  => 'Aktif',
            ],
        ];
    }

    /**
     * Ambil nama asli pengguna dari database lokal (`users` atau `group_members`) jika sudah valid.
     */
    public function getLocalUserName(string $identifier): ?string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        try {
            if (Schema::hasTable('users')) {
                $user = User::query()
                    ->where('username', $identifier)
                    ->orWhere('identifier', $identifier)
                    ->orWhere('nim', $identifier)
                    ->orWhere('nidn_nip', $identifier)
                    ->first();

                if ($user) {
                    $candidate = trim((string) ($user->name ?: $user->nama_lengkap));
                    if (! self::isPlaceholderName($candidate, $identifier)) {
                        return $candidate;
                    }
                }
            }

            if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'user_name')) {
                $memberNames = GroupMember::query()
                    ->where('user_identifier', $identifier)
                    ->whereNotNull('user_name')
                    ->pluck('user_name');

                foreach ($memberNames as $memberName) {
                    $candidate = trim((string) $memberName);
                    if (! self::isPlaceholderName($candidate, $identifier)) {
                        return $candidate;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal membaca nama lokal: '.$e->getMessage());
        }

        $seeds = $this->defaultSeedContacts();
        if (isset($seeds[$identifier])) {
            $seedName = trim((string) ($seeds[$identifier]['nama_lengkap'] ?? $seeds[$identifier]['name'] ?? ''));
            if (! self::isPlaceholderName($seedName, $identifier)) {
                return $seedName;
            }
        }

        return null;
    }

    /**
     * Simpan atau perbarui profil user ke database lokal (`users` & `group_members`) dengan Anti-Overwrite Guard.
     * Jika di database lokal sudah ada nama asli yang valid, tidak boleh ditimpa dengan placeholder.
     */
    public function saveLocalUserProfile(
        string $identifier,
        ?string $name,
        string $role = 'Mahasiswa',
        string $unit = 'Fakultas Teknik',
        ?string $prodi = 'Teknik Informatika'
    ): ?User {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $roleClean = strtolower(trim($role));
        $normalizedRole = match ($roleClean) {
            'dosen', 'admin_grup' => 'Dosen',
            'staff_unit', 'unit kerja', 'layanan' => 'Staff_Unit',
            'admin' => 'Admin',
            default => 'Mahasiswa',
        };

        $existingRealName = $this->getLocalUserName($identifier);
        $incomingClean = trim((string) $name);
        $incomingIsPlaceholder = self::isPlaceholderName($incomingClean, $identifier);

        // ATURAN KERAS (Anti-Overwrite Guard)
        if ($existingRealName !== null && $incomingIsPlaceholder) {
            $finalName = $existingRealName;
        } elseif (! $incomingIsPlaceholder) {
            $finalName = $incomingClean;
        } else {
            $finalName = $existingRealName
                ?? ($normalizedRole === 'Dosen' ? 'Dosen Pembimbing, M.Kom.' : 'Mahasiswa ('.$identifier.')');
        }

        // Lindungi juga session('sso_user') jika yang sedang diperbarui adalah pengguna aktif
        try {
            $sessionUser = session('sso_user');
            if (is_array($sessionUser) && (string) ($sessionUser['identifier'] ?? '') === $identifier) {
                $sessionCurrentName = (string) ($sessionUser['nama_lengkap'] ?? $sessionUser['name'] ?? '');
                if (! self::isPlaceholderName($finalName, $identifier)) {
                    $sessionUser['name'] = $finalName;
                    $sessionUser['nama_lengkap'] = $finalName;
                    session(['sso_user' => $sessionUser]);
                } elseif (! self::isPlaceholderName($sessionCurrentName, $identifier)) {
                    $finalName = $sessionCurrentName;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika di luar konteks HTTP session
        }

        $userModel = null;

        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA temp_store = MEMORY;');
                DB::statement('PRAGMA synchronous = NORMAL;');
            }

            if (Schema::hasTable('users')) {
                $roleId = DB::table('roles')->where('nama_role', $normalizedRole)->value('id')
                    ?? DB::table('roles')->value('id')
                    ?? 1;

                $fakultasId = DB::table('fakultas')->value('id');
                $prodiId = DB::table('program_studi')->value('id');

                $existingUser = User::query()
                    ->where('username', $identifier)
                    ->orWhere('identifier', $identifier)
                    ->first();

                if ($existingUser) {
                    $existingDbName = trim((string) ($existingUser->name ?: $existingUser->nama_lengkap));
                    if (! self::isPlaceholderName($existingDbName, $identifier) && self::isPlaceholderName($finalName, $identifier)) {
                        $finalName = $existingDbName;
                    }

                    $updatePayload = [
                        'nama_lengkap' => $finalName,
                        'status_akun'  => 'Aktif',
                    ];

                    if (Schema::hasColumn('users', 'identifier')) {
                        $updatePayload['identifier'] = $identifier;
                    }
                    if (Schema::hasColumn('users', 'name')) {
                        $updatePayload['name'] = $finalName;
                    }
                    if (Schema::hasColumn('users', 'role_name')) {
                        $updatePayload['role_name'] = strtolower($normalizedRole);
                    }
                    if (Schema::hasColumn('users', 'unit')) {
                        $updatePayload['unit'] = $unit ?: ($prodi ?: 'Fakultas Teknik');
                    }

                    $existingUser->fill($updatePayload)->save();
                    $userModel = $existingUser;
                } else {
                    $createPayload = [
                        'role_id'          => $roleId,
                        'nama_lengkap'     => $finalName,
                        'username'         => $identifier,
                        'email_kampus'     => $identifier.'@unuja.ac.id',
                        'password'         => app()->isLocal() ? Hash::make($identifier) : Hash::make(Str::random(32)),
                        'status_akun'      => 'Aktif',
                        'nim'              => $normalizedRole === 'Mahasiswa' ? $identifier : null,
                        'nidn_nip'         => $normalizedRole === 'Dosen' ? $identifier : null,
                        'tahun_angkatan'   => $normalizedRole === 'Mahasiswa' ? 2024 : null,
                        'jabatan_akademik' => $normalizedRole === 'Dosen' ? 'Dosen Pengampu' : null,
                        'prodi_id'         => $prodiId,
                        'fakultas_id'      => $fakultasId,
                    ];

                    if (Schema::hasColumn('users', 'identifier')) {
                        $createPayload['identifier'] = $identifier;
                    }
                    if (Schema::hasColumn('users', 'name')) {
                        $createPayload['name'] = $finalName;
                    }
                    if (Schema::hasColumn('users', 'role_name')) {
                        $createPayload['role_name'] = strtolower($normalizedRole);
                    }
                    if (Schema::hasColumn('users', 'unit')) {
                        $createPayload['unit'] = $unit ?: ($prodi ?: 'Fakultas Teknik');
                    }

                    $userModel = User::create($createPayload);
                }
            }

            // Sinkronkan juga kolom user_name di tabel group_members (dengan Anti-Overwrite Guard)
            if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'user_name')) {
                if (! self::isPlaceholderName($finalName, $identifier)) {
                    GroupMember::where('user_identifier', $identifier)->update([
                        'user_name' => $finalName,
                    ]);
                } else {
                    GroupMember::where('user_identifier', $identifier)
                        ->whereNull('user_name')
                        ->update([
                            'user_name' => $finalName,
                        ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Gagal menyimpan profil lokal untuk {$identifier}: ".$e->getMessage());
        }

        return $userModel;
    }

    /**
     * Resolusi Nama Asli (`getRealProfileName`) dengan prioritas Database Lokal -> API Kampus -> Anti-Overwrite Guard.
     */
    public function getRealProfileName(string $identifier, string $role = 'mahasiswa'): string
    {
        $identifier = trim($identifier);
        $role = strtolower(trim($role));

        if (self::isDosenIdentifier($identifier)) {
            $role = 'dosen';
        }

        // 1. Cek di database lokal terlebih dahulu (users / group_members)
        $localName = $this->getLocalUserName($identifier);
        if ($localName !== null && ! self::isPlaceholderName($localName, $identifier)) {
            return $localName;
        }

        // 2. Jika belum ada di DB lokal, tarik dari API Kampus UNUJA
        try {
            if ($role === 'mahasiswa') {
                // Validasi format NIM mahasiswa UNUJA (10 digit angka) agar NIM salah ketik 9 digit langsung ditolak
                if (preg_match('/^\d{10}$/', $identifier) !== 1) {
                    return 'Mahasiswa ('.$identifier.')';
                }

                // Coba filter: mahasiswa dengan parameter search maupun nim
                foreach ([
                    ['search' => $identifier],
                    ['nim' => $identifier],
                ] as $params) {
                    $rows = $this->apiClient->fetchMahasiswa($params);
                    foreach ($rows as $row) {
                        $rowNim = trim((string) ($row['nim'] ?? $row['identifier'] ?? ''));
                        if ($rowNim === $identifier) {
                            $apiName = trim((string) ($row['nama'] ?? $row['nama_mahasiswa'] ?? $row['nama_penduduk'] ?? $row['nama_lengkap'] ?? ''));
                            if (! self::isPlaceholderName($apiName, $identifier)) {
                                $prodi = (string) ($row['prodi'] ?? $row['nama_prodi'] ?? 'Teknik Informatika');
                                $fakultas = (string) ($row['fakultas'] ?? $row['nama_fakultas'] ?? 'Fakultas Teknik');
                                if (! str_starts_with(strtolower($fakultas), 'fakultas')) {
                                    $fakultas = 'Fakultas '.$fakultas;
                                }
                                $this->saveLocalUserProfile($identifier, $apiName, 'Mahasiswa', $fakultas, $prodi);

                                return $apiName;
                            }
                        }
                    }
                }

                // Fallback ke filter: mahasiswa_matakuliah karena setiap baris KRS menyertakan field "nama" mahasiswa asli
                $krsRows = $this->apiClient->fetchMahasiswaMatakuliah([
                    'nim' => $identifier,
                ]);

                if (! empty($krsRows)) {
                    $apiName = trim((string) ($krsRows[0]['nama'] ?? $krsRows[0]['nama_mahasiswa'] ?? $krsRows[0]['nama_penduduk'] ?? ''));
                    if (! self::isPlaceholderName($apiName, $identifier)) {
                        $this->saveLocalUserProfile($identifier, $apiName, 'Mahasiswa', 'Fakultas Teknik', 'Teknik Informatika');

                        return $apiName;
                    }
                }
            }

            if ($role === 'dosen') {
                $dosenList = $this->apiClient->fetchDosen();

                foreach ($dosenList as $dosen) {
                    $nidn = trim((string) ($dosen['nidn'] ?? ''));
                    $idPenduduk = trim((string) ($dosen['id_penduduk'] ?? ''));

                    if ($nidn === $identifier || $idPenduduk === $identifier) {
                        $fullName = self::formatDosenFullName($dosen);
                        if (! self::isPlaceholderName($fullName, $identifier)) {
                            $prodi = (string) ($dosen['prodi'] ?? 'Teknik Informatika');
                            $fakultas = (string) ($dosen['fakultas'] ?? 'Fakultas Teknik');
                            if (! str_starts_with(strtolower($fakultas), 'fakultas')) {
                                $fakultas = 'Fakultas '.$fakultas;
                            }
                            $this->saveLocalUserProfile($identifier, $fullName, 'Dosen', $fakultas, $prodi);

                            return $fullName;
                        }
                    }
                }

                // Jika dosen berasal dari unit MKDU lintas fakultas, cek di dosen_matakuliah
                $dosenMkRows = $this->apiClient->fetchDosenMatakuliah([
                    'id_sms' => UnujaApiClient::TI_ID_SMS,
                ]);

                foreach ($dosenMkRows as $row) {
                    $nidn = trim((string) ($row['nidn'] ?? ''));
                    $idPenduduk = trim((string) ($row['id_penduduk'] ?? ''));

                    if ($nidn === $identifier || $idPenduduk === $identifier) {
                        $fullName = self::formatDosenFullName($row);
                        if (! self::isPlaceholderName($fullName, $identifier)) {
                            $this->saveLocalUserProfile($identifier, $fullName, 'Dosen', 'Fakultas Teknik', 'Teknik Informatika');

                            return $fullName;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Gagal menarik nama profil dari API: '.$e->getMessage());
        }

        // 3. Jika API sedang lambat/gagal, cek ulang DB lokal agar tidak pernah mereset nama asli
        $fallbackLocal = $this->getLocalUserName($identifier);
        if ($fallbackLocal !== null && ! self::isPlaceholderName($fallbackLocal, $identifier)) {
            return $fallbackLocal;
        }

        return $role === 'dosen' ? 'Dosen ('.$identifier.')' : 'Mahasiswa ('.$identifier.')';
    }

    /**
     * Cari profil pengguna berdasarkan NIM / NIDN / Identifier dari DB Lokal, Direktori Cache, atau API SSO.
     * Hanya mengembalikan profil jika benar-benar terverifikasi di DB lokal atau API kampus.
     *
     * @return array<string, mixed>|null
     */
    public function findUserByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        // 1. Cek di tabel users lokal terlebih dahulu
        $localName = $this->getLocalUserName($identifier);
        $directory = $this->getDirectoryContacts();

        if (isset($directory[$identifier])) {
            $cachedProfile = $directory[$identifier];
            $cachedName = (string) ($cachedProfile['nama_lengkap'] ?? $cachedProfile['name'] ?? '');

            if (! self::isPlaceholderName($cachedName, $identifier)) {
                $this->saveLocalUserProfile(
                    $identifier,
                    $cachedName,
                    (string) ($cachedProfile['role'] ?? 'Mahasiswa'),
                    (string) ($cachedProfile['unit'] ?? $cachedProfile['fakultas'] ?? 'Fakultas Teknik'),
                    (string) ($cachedProfile['prodi'] ?? 'Teknik Informatika')
                );

                return $cachedProfile;
            }
        }

        if ($localName !== null && ! self::isPlaceholderName($localName, $identifier)) {
            $isDosen = self::isDosenIdentifier($identifier);
            $roleLabel = $isDosen ? 'Dosen' : 'Mahasiswa';

            if (Schema::hasTable('users')) {
                $dbUser = User::where('username', $identifier)->orWhere('identifier', $identifier)->first();
                if ($dbUser) {
                    $roleLabel = $dbUser->roleName();
                }
            }

            $profile = [
                'identifier'   => $identifier,
                'username'     => $identifier,
                'nim'          => $roleLabel === 'Mahasiswa' ? $identifier : null,
                'nidn_nip'     => $roleLabel === 'Dosen' ? $identifier : null,
                'name'         => $localName,
                'nama_lengkap' => $localName,
                'role'         => $roleLabel,
                'prodi'        => 'Teknik Informatika',
                'fakultas'     => 'Fakultas Teknik',
                'unit'         => 'Fakultas Teknik',
                'status_akun'  => 'Aktif',
            ];

            $this->upsertDirectoryContacts([$identifier => $profile]);

            return $profile;
        }

        // 2. Cari ke API Portal SSO UNUJA (filter mahasiswa lalu dosen)
        $isLikelyDosen = self::isDosenIdentifier($identifier);
        $resolvedName = $this->getRealProfileName($identifier, $isLikelyDosen ? 'dosen' : 'mahasiswa');

        if (! self::isPlaceholderName($resolvedName, $identifier)) {
            $roleLabel = $isLikelyDosen ? 'Dosen' : 'Mahasiswa';
            $profile = [
                'identifier'   => $identifier,
                'username'     => $identifier,
                'nim'          => $roleLabel === 'Mahasiswa' ? $identifier : null,
                'nidn_nip'     => $roleLabel === 'Dosen' ? $identifier : null,
                'name'         => $resolvedName,
                'nama_lengkap' => $resolvedName,
                'role'         => $roleLabel,
                'prodi'        => 'Teknik Informatika',
                'fakultas'     => 'Fakultas Teknik',
                'unit'         => 'Fakultas Teknik',
                'status_akun'  => 'Aktif',
            ];

            $this->saveLocalUserProfile($identifier, $resolvedName, $roleLabel);
            $this->upsertDirectoryContacts([$identifier => $profile]);

            return $profile;
        }

        return null;
    }

    /**
     * Cari mahasiswa/dosen langsung ke API Kampus berdasarkan keyword (Nama atau NIM) lalu simpan ke DB lokal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchUsersFromApi(string $keyword): array
    {
        $keyword = trim($keyword);
        if (strlen($keyword) < 3) {
            return [];
        }

        $matchedProfiles = [];

        try {
            $rows = $this->apiClient->fetchMahasiswa([
                'search' => $keyword,
            ]);

            // Jika keyword adalah NIM 10 digit dan filter mahasiswa kosong, coba dari mahasiswa_matakuliah
            if (empty($rows) && preg_match('/^\d{10}$/', $keyword) === 1) {
                $krsRows = $this->apiClient->fetchMahasiswaMatakuliah([
                    'nim' => $keyword,
                ]);

                if (! empty($krsRows)) {
                    $rows = [[
                        'nim'      => (string) ($krsRows[0]['nim'] ?? $keyword),
                        'nama'     => (string) ($krsRows[0]['nama'] ?? ''),
                        'prodi'    => 'Teknik Informatika',
                        'fakultas' => 'Fakultas Teknik',
                    ]];
                }
            }

            $toUpsert = [];

            foreach (array_slice($rows, 0, 30) as $row) {
                $nim = trim((string) ($row['nim'] ?? $row['identifier'] ?? ''));
                $nama = trim((string) ($row['nama'] ?? $row['nama_mahasiswa'] ?? $row['nama_penduduk'] ?? ''));

                if ($nim === '' || preg_match('/^\d{10}$/', $nim) !== 1 || self::isPlaceholderName($nama, $nim)) {
                    continue;
                }

                $prodi = (string) ($row['prodi'] ?? $row['nama_prodi'] ?? 'Teknik Informatika');
                $fakultas = (string) ($row['fakultas'] ?? $row['nama_fakultas'] ?? 'Fakultas Teknik');
                if (! str_starts_with(strtolower($fakultas), 'fakultas')) {
                    $fakultas = 'Fakultas '.$fakultas;
                }

                $this->saveLocalUserProfile($nim, $nama, 'Mahasiswa', $fakultas, $prodi);

                $profile = [
                    'identifier'     => $nim,
                    'username'       => $nim,
                    'nim'            => $nim,
                    'name'           => $nama,
                    'nama_lengkap'   => $nama,
                    'role'           => 'Mahasiswa',
                    'prodi'          => $prodi,
                    'fakultas'       => $fakultas,
                    'unit'           => $fakultas,
                    'tahun_angkatan' => (int) substr((string) ($row['tahun_masuk'] ?? '2024'), 0, 4) ?: 2024,
                    'status_akun'    => 'Aktif',
                ];

                $toUpsert[$nim] = $profile;
                $matchedProfiles[] = $profile;
            }

            if (! empty($toUpsert)) {
                $this->upsertDirectoryContacts($toUpsert);
            }
        } catch (\Throwable $e) {
            Log::warning('Pencarian API gagal: '.$e->getMessage());
        }

        return $matchedProfiles;
    }

    /**
     * Ambil seluruh direktori kontak (Mahasiswa, Dosen, Unit Layanan, Admin) dari DB Lokal (`users` & `group_members`) + Cache.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDirectoryContacts(): array
    {
        $contacts = $this->defaultSeedContacts();

        // 1. Gabungkan dari Cache jika ada
        try {
            $cached = Cache::get(self::DIRECTORY_CACHE_KEY);
            if (is_array($cached)) {
                foreach ($cached as $id => $profile) {
                    $key = (string) ($profile['identifier'] ?? $id);
                    if ($key === '') {
                        continue;
                    }
                    if (($profile['role'] ?? '') === 'Unit Kerja') {
                        $profile['role'] = 'Staff_Unit';
                    }

                    // Anti-Overwrite Guard pada level direktori
                    $existingName = (string) ($contacts[$key]['nama_lengkap'] ?? $contacts[$key]['name'] ?? '');
                    $incomingName = (string) ($profile['nama_lengkap'] ?? $profile['name'] ?? '');
                    if (! self::isPlaceholderName($existingName, $key) && self::isPlaceholderName($incomingName, $key)) {
                        $profile['name'] = $existingName;
                        $profile['nama_lengkap'] = $existingName;
                    }

                    $contacts[$key] = array_merge($contacts[$key] ?? [], $profile);
                }
            }
        } catch (\Throwable $e) {
            // Lanjutkan dengan database lokal
        }

        // 2. Gabungkan dari tabel `users` lokal (Sumber Kebenaran Utama)
        try {
            if (Schema::hasTable('users')) {
                $dbUsers = User::all();
                foreach ($dbUsers as $dbUser) {
                    $key = trim((string) ($dbUser->identifier ?: $dbUser->username));
                    if ($key === '') {
                        continue;
                    }

                    $dbName = trim((string) ($dbUser->name ?: $dbUser->nama_lengkap));
                    $existingName = (string) ($contacts[$key]['nama_lengkap'] ?? $contacts[$key]['name'] ?? '');

                    // Jangan timpa nama asli dengan placeholder
                    if (self::isPlaceholderName($dbName, $key) && ! self::isPlaceholderName($existingName, $key)) {
                        $dbName = $existingName;
                    }

                    $roleLabel = $dbUser->roleName();

                    $contacts[$key] = array_merge($contacts[$key] ?? [], [
                        'identifier'     => $key,
                        'username'       => $key,
                        'nim'            => $roleLabel === 'Mahasiswa' ? $key : null,
                        'nidn_nip'       => $roleLabel === 'Dosen' ? $key : null,
                        'name'           => $dbName !== '' ? $dbName : $key,
                        'nama_lengkap'   => $dbName !== '' ? $dbName : $key,
                        'role'           => $roleLabel,
                        'prodi'          => $roleLabel === 'Staff_Unit' || $roleLabel === 'Admin' ? null : 'Teknik Informatika',
                        'fakultas'       => $roleLabel === 'Staff_Unit' || $roleLabel === 'Admin' ? null : 'Fakultas Teknik',
                        'unit'           => (string) ($dbUser->unit ?: 'Fakultas Teknik'),
                        'tahun_angkatan' => $dbUser->tahun_angkatan ?? ($roleLabel === 'Mahasiswa' ? 2024 : null),
                        'status_akun'    => (string) ($dbUser->status_akun ?: 'Aktif'),
                    ]);
                }
            }

            // 3. Pastikan semua anggota di `group_members` juga terdaftar di direktori kontak
            if (Schema::hasTable('group_members')) {
                $members = GroupMember::all();
                foreach ($members as $member) {
                    $key = trim((string) $member->user_identifier);
                    if ($key === '') {
                        continue;
                    }

                    $memberRole = strtolower((string) $member->role) === 'admin' ? 'Dosen' : 'Mahasiswa';
                    $memberName = trim((string) ($member->user_name ?? ''));
                    $existingName = (string) ($contacts[$key]['nama_lengkap'] ?? $contacts[$key]['name'] ?? '');

                    if (! self::isPlaceholderName($existingName, $key)) {
                        $finalName = $existingName;
                    } elseif (! self::isPlaceholderName($memberName, $key)) {
                        $finalName = $memberName;
                    } else {
                        $finalName = $memberRole === 'Dosen' ? 'Dosen Pembimbing, M.Kom.' : 'Mahasiswa ('.$key.')';
                    }

                    $contacts[$key] = array_merge($contacts[$key] ?? [], [
                        'identifier'   => $key,
                        'username'     => $key,
                        'nim'          => $memberRole === 'Mahasiswa' ? $key : null,
                        'nidn_nip'     => $memberRole === 'Dosen' ? $key : null,
                        'name'         => $finalName,
                        'nama_lengkap' => $finalName,
                        'role'         => $contacts[$key]['role'] ?? $memberRole,
                        'prodi'        => $contacts[$key]['prodi'] ?? 'Teknik Informatika',
                        'fakultas'     => $contacts[$key]['fakultas'] ?? 'Fakultas Teknik',
                        'unit'         => $contacts[$key]['unit'] ?? 'Fakultas Teknik',
                        'status_akun'  => 'Aktif',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat direktori dari DB lokal: '.$e->getMessage());
        }

        return $contacts;
    }

    /**
     * Tambahkan atau perbarui kumpulan kontak ke dalam Database Lokal & Cache dengan Anti-Overwrite Guard.
     *
     * @param  array<string, array<string, mixed>>  $newContacts
     */
    public function upsertDirectoryContacts(array $newContacts): void
    {
        if (empty($newContacts)) {
            return;
        }

        $current = $this->getDirectoryContacts();

        foreach ($newContacts as $id => $profile) {
            $key = trim((string) ($profile['identifier'] ?? $id));
            if ($key === '') {
                continue;
            }

            $existingName = (string) ($current[$key]['nama_lengkap'] ?? $current[$key]['name'] ?? '');
            $incomingName = (string) ($profile['nama_lengkap'] ?? $profile['name'] ?? '');

            // ATURAN KERAS (Anti-Overwrite Guard)
            if (! self::isPlaceholderName($existingName, $key) && self::isPlaceholderName($incomingName, $key)) {
                $profile['name'] = $existingName;
                $profile['nama_lengkap'] = $existingName;
            }

            $current[$key] = array_merge($current[$key] ?? [], $profile, [
                'identifier' => $key,
                'username'   => $key,
            ]);

            $this->saveLocalUserProfile(
                $key,
                (string) ($current[$key]['nama_lengkap'] ?? $current[$key]['name'] ?? ''),
                (string) ($current[$key]['role'] ?? 'Mahasiswa'),
                (string) ($current[$key]['unit'] ?? $current[$key]['fakultas'] ?? 'Fakultas Teknik'),
                (string) ($current[$key]['prodi'] ?? 'Teknik Informatika')
            );
        }

        try {
            Cache::put(self::DIRECTORY_CACHE_KEY, $current, 21600);
        } catch (\Throwable $e) {
            // Abaikan jika cache gagal ditulis, karena data sudah aman di tabel users SQLite
        }
    }

    /**
     * Hapus cache direktori kontak agar dibangun ulang dari database lokal.
     */
    public function forgetDirectoryCache(): void
    {
        Cache::forget(self::DIRECTORY_CACHE_KEY);
    }

    /**
     * Segarkan cache direktori kontak dari database lokal.
     *
     * @return array<string, array<string, mixed>>
     */
    public function refreshDirectoryCache(): array
    {
        Cache::forget(self::DIRECTORY_CACHE_KEY);
        $freshDirectory = $this->getDirectoryContacts();

        try {
            Cache::put(self::DIRECTORY_CACHE_KEY, $freshDirectory, 21600);
        } catch (\Throwable $e) {
            // Abaikan
        }

        return $freshDirectory;
    }

    /**
     * Konversi array profil SSO menjadi instance App\Models\User in-memory yang kompatibel dengan semua komponen Blade.
     *
     * @param  array<string, mixed>  $profile
     */
    public function toUserObject(array $profile): User
    {
        $user = new User();
        $user->incrementing = false;
        $user->keyType = 'string';

        $identifier = trim((string) ($profile['identifier'] ?? $profile['username'] ?? ''));
        $rawName = trim((string) ($profile['nama_lengkap'] ?? $profile['name'] ?? $identifier));

        // Anti-Overwrite Guard saat merender objek User
        if ($identifier !== '' && ! str_starts_with($identifier, 'group_') && self::isPlaceholderName($rawName, $identifier)) {
            $localRealName = $this->getLocalUserName($identifier);
            if ($localRealName !== null) {
                $rawName = $localRealName;
            }
        }

        $nama = $rawName !== '' ? $rawName : $identifier;
        $rawRole = (string) ($profile['role'] ?? 'Mahasiswa');
        $roleName = match (strtolower($rawRole)) {
            'dosen', 'admin_grup' => 'Dosen',
            'unit kerja', 'staff_unit', 'layanan' => 'Staff_Unit',
            'admin' => 'Admin',
            'group' => 'Group',
            default => 'Mahasiswa',
        };

        $prodiName = $profile['prodi'] ?? ($roleName === 'Mahasiswa' || $roleName === 'Dosen' ? 'Teknik Informatika' : null);
        $fakultasName = $profile['fakultas'] ?? ($roleName === 'Mahasiswa' || $roleName === 'Dosen' ? 'Fakultas Teknik' : null);
        $unitName = $profile['unit'] ?? $fakultasName;

        $nim = $profile['nim'] ?? ($roleName === 'Mahasiswa' ? $identifier : null);
        $nidnNip = $profile['nidn_nip'] ?? ($roleName === 'Dosen' ? $identifier : null);
        $kodeUnit = $profile['kode_unit'] ?? ($roleName === 'Staff_Unit' ? strtoupper(str_replace('unit_', '', $identifier)) : null);

        $user->forceFill([
            'id'               => $identifier,
            'identifier'       => $identifier,
            'username'         => (string) ($profile['username'] ?? $identifier),
            'name'             => $nama,
            'nama_lengkap'     => $nama,
            'role_name'        => strtolower($roleName),
            'unit'             => $unitName,
            'status_akun'      => (string) ($profile['status_akun'] ?? 'Aktif'),
            'nidn_nip'         => $nidnNip,
            'nim'              => $nim,
            'jabatan_akademik' => $profile['jabatan_akademik'] ?? ($roleName === 'Dosen' ? 'Dosen Pengampu' : null),
            'tahun_angkatan'   => $profile['tahun_angkatan'] ?? ($roleName === 'Mahasiswa' ? 2024 : null),
            'email_kampus'     => $profile['email_kampus'] ?? ($identifier.'@unuja.ac.id'),
        ]);

        $user->setRelation('role', (object) [
            'id'        => 1,
            'nama_role' => $roleName,
            'name'      => $roleName,
            'label'     => str_replace('_', ' ', $roleName),
            'deskripsi' => $unitName ?? $prodiName ?? $roleName,
        ]);

        $user->setRelation('prodi', $prodiName ? (object) [
            'id'         => 1,
            'kode_prodi' => 'IF',
            'nama_prodi' => $prodiName,
            'name'       => $prodiName,
            'label'      => $prodiName,
        ] : null);

        $user->setRelation('fakultas', $fakultasName ? (object) [
            'id'            => 1,
            'kode_fakultas' => 'FT',
            'nama_fakultas' => $fakultasName,
            'name'          => $fakultasName,
            'label'         => $fakultasName,
        ] : null);

        $user->setRelation('unitKampus', $unitName ? (object) [
            'id'                => 1,
            'kode_unit'         => $kodeUnit ?? '-',
            'nama_unit'         => $unitName,
            'nama_unit_kerja'   => $unitName,
            'deskripsi_layanan' => $profile['deskripsi_layanan'] ?? $unitName,
            'name'              => $unitName,
            'label'             => $unitName,
        ] : null);

        return $user;
    }
}
