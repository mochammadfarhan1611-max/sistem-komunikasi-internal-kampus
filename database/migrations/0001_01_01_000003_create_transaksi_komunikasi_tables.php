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
        // 12. Sinkronisasi Akademik
        Schema::create('sinkronisasi_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->enum('kategori_data', [
                'Mahasiswa',
                'Dosen',
                'Mata_Kuliah',
                'Kelas',
                'Perwalian_Bimbingan',
                'KRS',
                'Semua_Data',
            ]);
            $table->unsignedInteger('total_data_tersinkron')->default(0);
            $table->enum('status_sinkronisasi', ['Berhasil', 'Gagal', 'Sebagian']);
            $table->text('keterangan')->nullable();
            $table->timestamp('waktu_sinkronisasi')->useCurrent();
        });

        // 13. KRS Mahasiswa
        Schema::create('krs_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_perkuliahan_id')->constrained('kelas_perkuliahan')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            $table->timestamp('waktu_sinkronisasi')->useCurrent();
            $table->unique(['mahasiswa_id', 'kelas_perkuliahan_id']);
        });

        // 14. Grup Chat
        Schema::create('grup_chat', function (Blueprint $table) {
            $table->id();
            $table->string('kode_referensi', 100)->nullable()->unique();
            $table->string('nama_grup', 150);
            $table->foreignId('jenis_grup_id')->constrained('jenis_grup')->restrictOnDelete();
            $table->foreignId('kelas_perkuliahan_id')->nullable()->constrained('kelas_perkuliahan')->nullOnDelete();
            $table->foreignId('tahun_akademik_id')->nullable()->constrained('tahun_akademik')->nullOnDelete();
            $table->foreignId('pembuat_id')->constrained('users')->restrictOnDelete();
            $table->enum('status_grup', ['Aktif', 'Arsip', 'Nonaktif'])->default('Aktif');
            $table->timestamp('waktu_dibuat')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 15. Anggota Grup
        Schema::create('anggota_grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grup_id')->constrained('grup_chat')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('peran_anggota', ['Admin_Grup', 'Anggota'])->default('Anggota');
            $table->timestamp('terakhir_baca_pada')->nullable();
            $table->timestamp('waktu_bergabung')->useCurrent();
            $table->unique(['grup_id', 'user_id']);
        });

        // 16. Percakapan
        Schema::create('percakapan', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe_percakapan', ['Personal', 'Grup', 'Unit_Kampus']);
            $table->foreignId('user_satu_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_dua_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('grup_id')->nullable()->constrained('grup_chat')->cascadeOnDelete();
            $table->foreignId('unit_kampus_id')->nullable()->constrained('unit_kampus')->cascadeOnDelete();
            $table->enum('status_percakapan', ['Aktif', 'Arsip', 'Diblokir'])->default('Aktif');
            $table->timestamp('waktu_pesan_terakhir')->nullable();
            $table->timestamp('waktu_percakapan')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // 17. Pesan
        Schema::create('pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('percakapan_id')->constrained('percakapan')->cascadeOnDelete();
            $table->foreignId('pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->text('isi_pesan')->nullable();
            $table->boolean('dikirim_saat_libur')->default(false);
            $table->boolean('status_terkirim')->default(true);
            $table->boolean('status_dibaca')->default(false);
            $table->timestamp('waktu_pengiriman')->useCurrent();
        });

        // 18. Permintaan Chat
        Schema::create('permintaan_chat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('penerima_id')->constrained('users')->cascadeOnDelete();
            $table->text('alasan_pengantar');
            $table->enum('kategori_keperluan', [
                'Konsultasi_Akademik',
                'Kegiatan_Organisasi_Lomba',
                'Keperluan_Lainnya',
            ]);
            $table->enum('status_permintaan', ['Menunggu', 'Diterima', 'Ditolak'])->default('Menunggu');
            $table->string('catatan_respon')->nullable();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->cascadeOnDelete();
            $table->date('berlaku_sampai')->nullable();
            $table->foreignId('percakapan_id')->nullable()->constrained('percakapan')->nullOnDelete();
            $table->timestamp('waktu_pengajuan')->useCurrent();
            $table->timestamp('waktu_respon')->nullable();
        });

        // 19. Notifikasi
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('jenis_notifikasi', [
                'Pesan_Baru',
                'Permintaan_Chat',
                'Undangan_Grup',
                'Balasan_Unit_Kampus',
                'Info_Sistem',
            ]);
            $table->string('judul_notifikasi', 150);
            $table->text('isi_notifikasi');
            $table->boolean('is_dibaca')->default(false);
            $table->timestamp('waktu_notifikasi')->useCurrent();
        });

        // 20. Lampiran Pesan
        Schema::create('lampiran_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesan_id')->constrained('pesan')->cascadeOnDelete();
            $table->string('nama_file');
            $table->string('path_file');
            $table->enum('tipe_file', ['PDF', 'DOCX', 'XLSX', 'JPG', 'PNG']);
            $table->unsignedInteger('ukuran_kb');
            $table->timestamp('waktu_upload')->useCurrent();
        });

        // 21. Pengaturan Komunikasi
        Schema::create('pengaturan_komunikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('mode_jangan_ganggu')->default(false);
            $table->time('jam_mulai_dnd')->nullable();
            $table->time('jam_selesai_dnd')->nullable();
            $table->boolean('notifikasi_aktif')->default(true);
            $table->json('mute_percakapan_ids')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_komunikasi');
        Schema::dropIfExists('lampiran_pesan');
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('permintaan_chat');
        Schema::dropIfExists('pesan');
        Schema::dropIfExists('percakapan');
        Schema::dropIfExists('anggota_grup');
        Schema::dropIfExists('grup_chat');
        Schema::dropIfExists('krs_mahasiswa');
        Schema::dropIfExists('sinkronisasi_akademik');
    }
};

