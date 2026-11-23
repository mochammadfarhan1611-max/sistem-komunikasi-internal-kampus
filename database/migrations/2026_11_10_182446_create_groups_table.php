<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Contoh: "Bimbingan KRS - Ganjil 2026"
            $table->string('creator_identifier'); // NIDN Dosen Wali pembuat grup
            $table->string('kode_tahun_akademik')->nullable(); // Penanda semester
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};