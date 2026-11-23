<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\JenisGrup;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\TahunAkademik;
use App\Models\UnitKampus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed 4 Roles (idempoten)
        $roleMahasiswa = Role::firstOrCreate(
            ['nama_role' => 'Mahasiswa'],
            ['deskripsi' => 'Mahasiswa aktif kampus']
        );

        $roleDosen = Role::firstOrCreate(
            ['nama_role' => 'Dosen'],
            ['deskripsi' => 'Dosen pengampu, dosen wali, dan pembimbing akademik']
        );

        $roleStaffUnit = Role::firstOrCreate(
            ['nama_role' => 'Staff_Unit'],
            ['deskripsi' => 'Akun resmi unit kerja / bagian layanan kampus']
        );

        $roleAdmin = Role::firstOrCreate(
            ['nama_role' => 'Admin'],
            ['deskripsi' => 'Administrator Sistem Komunikasi Internal Kampus']
        );

        // 2. Seed Fakultas & Program Studi (idempoten)
        $fakultasTeknik = Fakultas::firstOrCreate(
            ['kode_fakultas' => 'FT'],
            ['nama_fakultas' => 'Fakultas Teknik']
        );

        Fakultas::firstOrCreate(
            ['kode_fakultas' => 'FEB'],
            ['nama_fakultas' => 'Fakultas Ekonomi dan Bisnis']
        );

        $prodiTI = ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'TI'],
            [
                'fakultas_id' => $fakultasTeknik->id,
                'nama_prodi' => 'Teknik Informatika',
            ]
        );

        ProgramStudi::firstOrCreate(
            ['kode_prodi' => 'SI'],
            [
                'fakultas_id' => $fakultasTeknik->id,
                'nama_prodi' => 'Sistem Informasi',
            ]
        );

        // 3. Seed Tahun Akademik Aktif (idempoten)
        TahunAkademik::firstOrCreate(
            ['kode_semester' => '20261'],
            [
                'nama_semester' => 'Semester Ganjil 2026/2027',
                'is_aktif' => true,
                'tanggal_mulai' => '2026-09-01',
                'tanggal_selesai' => '2027-01-31',
            ]
        );

        // 4. Seed Semua 7 Jenis Grup (idempoten)
        $daftarJenisGrup = [
            ['nama_jenis' => 'Grup_Kelas', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_Angkatan', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_Dosen_Wali', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_PKL', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_KKN', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_Skripsi', 'is_otomatis' => true],
            ['nama_jenis' => 'Grup_Custom', 'is_otomatis' => false],
        ];

        foreach ($daftarJenisGrup as $jenis) {
            JenisGrup::firstOrCreate(
                ['nama_jenis' => $jenis['nama_jenis']],
                ['is_otomatis' => $jenis['is_otomatis']]
            );
        }

        // 5. Seed 4 Akun User (password: password) & 1 Unit Kampus (idempoten)
        $defaultPassword = Hash::make('password');

        // Akun Admin
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'role_id' => $roleAdmin->id,
                'nama_lengkap' => 'Administrator Sistem',
                'email_kampus' => 'admin@kampus.ac.id',
                'password' => $defaultPassword,
                'status_akun' => 'Aktif',
            ]
        );

        // Akun Dosen
        User::firstOrCreate(
            ['username' => '0711028801'],
            [
                'role_id' => $roleDosen->id,
                'nama_lengkap' => 'Dr. Budi Santoso, S.Kom., M.Kom.',
                'email_kampus' => '0711028801@dosen.kampus.ac.id',
                'password' => $defaultPassword,
                'status_akun' => 'Aktif',
                'nidn_nip' => '0711028801',
                'jabatan_akademik' => 'Lektor',
                'prodi_id' => $prodiTI->id,
                'fakultas_id' => $fakultasTeknik->id,
            ]
        );

        // Akun Mahasiswa
        User::firstOrCreate(
            ['username' => '2421400177'],
            [
                'role_id' => $roleMahasiswa->id,
                'nama_lengkap' => 'Mochammad Farhan',
                'email_kampus' => '2421400177@mahasiswa.kampus.ac.id',
                'password' => $defaultPassword,
                'status_akun' => 'Aktif',
                'nim' => '2421400177',
                'tahun_angkatan' => 2024,
                'prodi_id' => $prodiTI->id,
                'fakultas_id' => $fakultasTeknik->id,
            ]
        );

        // Akun Staff Unit
        $userStaff = User::firstOrCreate(
            ['username' => 'unit_baak'],
            [
                'role_id' => $roleStaffUnit->id,
                'nama_lengkap' => 'Biro Administrasi Akademik & Kemahasiswaan',
                'email_kampus' => 'baak@kampus.ac.id',
                'password' => $defaultPassword,
                'status_akun' => 'Aktif',
            ]
        );

        // Data Unit Kampus yang terelasi dengan akun Staff Unit
        UnitKampus::firstOrCreate(
            ['kode_unit' => 'BAAK'],
            [
                'user_id' => $userStaff->id,
                'nama_unit_kerja' => 'Biro Administrasi Akademik & Kemahasiswaan',
                'deskripsi_layanan' => 'Melayani administrasi akademik, KRS, KHS, cuti akademik, dan layanan kemahasiswaan.',
            ]
        );
    }
}
