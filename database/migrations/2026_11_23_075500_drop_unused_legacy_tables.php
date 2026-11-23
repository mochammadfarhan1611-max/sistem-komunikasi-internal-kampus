<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop unused legacy tables in child-to-parent foreign key order.
     */
    public function up(): void
    {
        Schema::dropIfExists('lampiran_pesan');
        Schema::dropIfExists('permintaan_chat');
        Schema::dropIfExists('pesan');
        Schema::dropIfExists('percakapan');
        Schema::dropIfExists('anggota_grup');
        Schema::dropIfExists('grup_chat');
        Schema::dropIfExists('krs_mahasiswa');
        Schema::dropIfExists('kelas_perkuliahan');
        Schema::dropIfExists('mata_kuliah');
        Schema::dropIfExists('hubungan_akademik');
        Schema::dropIfExists('hari_libur_kampus');
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('pengaturan_komunikasi');
        Schema::dropIfExists('sinkronisasi_akademik');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('anti_spam_logs');
        Schema::dropIfExists('error_logs');
    }

    /**
     * Reverse the migrations.
     *
     * Legacy tables are intentionally not recreated on rollback; refer to
     * migrations 0001_01_01_000000, 0001_01_01_000003, and 0001_01_01_000004
     * for their original schema definitions.
     */
    public function down(): void
    {
        //
    }
};
