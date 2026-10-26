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
        // 22. Login Logs
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username_dicoba', 50);
            $table->enum('status_login', ['Berhasil', 'Gagal']);
            $table->string('info_perangkat_session')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('waktu_login')->useCurrent();
        });

        // 23. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('jenis_aktivitas', [
                'Buat_Grup_Custom',
                'Tambah_Anggota',
                'Ubah_Profil',
                'Ubah_Pengaturan',
                'Kirim_Pesan',
                'Kirim_File',
            ]);
            $table->string('deskripsi_aktivitas');
            $table->timestamp('waktu_aktivitas')->useCurrent();
        });

        // 24. Communication Logs
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->string('tujuan_komunikasi', 150);
            $table->enum('tipe_komunikasi', ['Personal', 'Grup', 'Unit_Kampus']);
            $table->timestamp('waktu_pengiriman')->useCurrent();
        });

        // 25. Anti Spam Logs
        Schema::create('anti_spam_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_pesan_singkat');
            $table->enum('status_peringatan', ['Peringatan_1', 'Peringatan_2', 'Cooldown_Aktif']);
            $table->boolean('status_cooldown')->default(true);
            $table->timestamp('cooldown_sampai')->nullable();
            $table->timestamp('waktu_kejadian')->useCurrent();
        });

        // 26. Error Logs
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('jenis_error', [
                'Gagal_Kirim_Pesan',
                'Gagal_Upload_File',
                'Gagal_Login',
                'Gagal_Sinkronisasi_Akademik',
                'Error_Antar_Service',
            ]);
            $table->text('detail_error');
            $table->timestamp('waktu_kejadian')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('anti_spam_logs');
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('login_logs');
    }
};

