<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Master Role
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->enum('nama_role', ['Mahasiswa', 'Dosen', 'Staff_Unit', 'Admin'])->unique();
            $table->string('deskripsi')->nullable();
            $table->timestamps();
        });

        // 2. Master Fakultas
        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('kode_fakultas', 20)->unique();
            $table->string('nama_fakultas', 150);
            $table->timestamps();
        });

        // 3. Master Program Studi
        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')->constrained('fakultas')->cascadeOnDelete();
            $table->string('kode_prodi', 20)->unique();
            $table->string('nama_prodi', 150);
            $table->timestamps();
        });

        // 4. Master Pengguna & Authentication (Tanpa Nomor HP Pribadi)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('nama_lengkap', 150);
            $table->string('username', 50)->unique()->comment('NIM / NIDN / Kode Akun Unit / Username Admin');
            $table->string('email_kampus', 150)->unique();
            $table->string('password');
            $table->string('foto_profil')->nullable();
            $table->enum('status_akun', ['Aktif', 'Nonaktif'])->default('Aktif');
            // Spesifik Mahasiswa
            $table->string('nim', 30)->nullable()->unique();
            $table->year('tahun_angkatan')->nullable();
            // Spesifik Dosen
            $table->string('nidn_nip', 30)->nullable()->unique();
            $table->string('jabatan_akademik', 100)->nullable();
            // Relasi Struktur Akademik
            $table->foreignId('prodi_id')->nullable()->constrained('program_studi')->nullOnDelete();
            $table->foreignId('fakultas_id')->nullable()->constrained('fakultas')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        // Tabel Session & Reset Password Bawaan Laravel
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 5. Master Unit / Bagian Kampus (1 Akun Resmi per Unit)
        Schema::create('unit_kampus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('kode_unit', 30)->unique();
            $table->string('nama_unit_kerja', 150);
            $table->text('deskripsi_layanan')->nullable();
            $table->timestamps();
        });

        // 6. Master Tahun Akademik / Semester
        Schema::create('tahun_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('kode_semester', 20)->unique();
            $table->string('nama_semester', 100);
            $table->boolean('is_aktif')->default(false);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();
        });

        // 7. Master Mata Kuliah
        Schema::create('mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodi_id')->constrained('program_studi')->cascadeOnDelete();
            $table->string('kode_mk', 30)->unique();
            $table->string('nama_mk', 150);
            $table->unsignedTinyInteger('sks');
            $table->timestamps();
        });

        // 8. Master Kelas Perkuliahan
        Schema::create('kelas_perkuliahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('users')->restrictOnDelete();
            $table->string('nama_kelas', 100);
            $table->timestamps();
        });

        // 9. Master Jenis Grup
        Schema::create('jenis_grup', function (Blueprint $table) {
            $table->id();
            $table->enum('nama_jenis', [
                'Grup_Kelas',
                'Grup_Angkatan',
                'Grup_Dosen_Wali',
                'Grup_PKL',
                'Grup_KKN',
                'Grup_Skripsi',
                'Grup_Custom',
            ])->unique();
            $table->boolean('is_otomatis')->default(true);
            $table->timestamps();
        });

        // 10. Master Hubungan Akademik
        Schema::create('hubungan_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('jenis_hubungan', [
                'Dosen_Pengampu',
                'Dosen_Wali',
                'Pembimbing_PKL',
                'Pembimbing_KKN',
                'Pembimbing_Skripsi',
                'Teman_Sekelas',
                'Teman_Seangkatan',
            ]);
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        // 11. Master Hari Libur Kampus
        Schema::create('hari_libur_kampus', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_libur')->unique();
            $table->string('nama_hari_libur', 150);
            $table->enum('jenis_libur', ['Libur_Nasional', 'Libur_Kampus']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hari_libur_kampus');
        Schema::dropIfExists('hubungan_akademik');
        Schema::dropIfExists('jenis_grup');
        Schema::dropIfExists('kelas_perkuliahan');
        Schema::dropIfExists('mata_kuliah');
        Schema::dropIfExists('tahun_akademik');
        Schema::dropIfExists('unit_kampus');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('program_studi');
        Schema::dropIfExists('fakultas');
        Schema::dropIfExists('roles');
    }
};
