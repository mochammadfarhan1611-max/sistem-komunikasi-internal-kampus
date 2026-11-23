<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'identifier')) {
                    $table->string('identifier', 50)->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('users', 'name')) {
                    $table->string('name', 150)->nullable()->after('nama_lengkap');
                }
                if (! Schema::hasColumn('users', 'role_name')) {
                    $table->string('role_name', 50)->nullable()->default('mahasiswa')->after('role_id');
                }
                if (! Schema::hasColumn('users', 'unit')) {
                    $table->string('unit', 150)->nullable()->default('Fakultas Teknik')->after('jabatan_akademik');
                }
            });
        }

        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table): void {
                if (! Schema::hasColumn('group_members', 'user_name')) {
                    $table->string('user_name', 150)->nullable()->after('user_identifier');
                }
            });
        }

        // Pastikan tabel master roles memiliki data dasar agar foreign key role_id pada tabel users selalu terpenuhi
        if (Schema::hasTable('roles')) {
            $roles = [
                ['nama_role' => 'Mahasiswa', 'deskripsi' => 'Mahasiswa Aktif UNUJA'],
                ['nama_role' => 'Dosen', 'deskripsi' => 'Dosen Pengampu / Wali UNUJA'],
                ['nama_role' => 'Staff_Unit', 'deskripsi' => 'Layanan Unit Kerja Kampus'],
                ['nama_role' => 'Admin', 'deskripsi' => 'Administrator Sistem'],
            ];

            foreach ($roles as $role) {
                DB::table('roles')->updateOrInsert(
                    ['nama_role' => $role['nama_role']],
                    [
                        'deskripsi'  => $role['deskripsi'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('fakultas')) {
            DB::table('fakultas')->updateOrInsert(
                ['kode_fakultas' => 'FT'],
                [
                    'nama_fakultas' => 'Fakultas Teknik',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );

            $fakultasId = DB::table('fakultas')->where('kode_fakultas', 'FT')->value('id');
            if ($fakultasId && Schema::hasTable('program_studi')) {
                DB::table('program_studi')->updateOrInsert(
                    ['kode_prodi' => 'IF'],
                    [
                        'fakultas_id' => $fakultasId,
                        'nama_prodi'  => 'Teknik Informatika',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'user_name')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->dropColumn('user_name');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                $columns = array_filter(
                    ['identifier', 'name', 'role_name', 'unit'],
                    fn (string $col): bool => Schema::hasColumn('users', $col)
                );
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
