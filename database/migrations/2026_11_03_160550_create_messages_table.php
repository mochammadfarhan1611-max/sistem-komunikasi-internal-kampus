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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Menggunakan string identifier (NIM/NIDN) bukan foreign key ke tabel users lokal
            $table->string('sender_identifier'); 
            $table->string('receiver_identifier')->nullable(); 
            
            // Relasi ke tabel groups (opsional, jika pesannya dikirim ke grup)
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            
            $table->text('body')->nullable();
            $table->string('attachment_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexing untuk mempercepat pencarian data
            $table->index(['sender_identifier', 'receiver_identifier']);
            $table->index(['receiver_identifier', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};