<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan kolom last_read_message_id pada group_members
        if (Schema::hasTable('group_members') && ! Schema::hasColumn('group_members', 'last_read_message_id')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->unsignedBigInteger('last_read_message_id')->nullable()->after('role');
            });
        }

        // Inisialisasi last_read_message_id untuk data lama ke ID pesan terakhir grup masing-masing agar tidak muncul ratusan notif palsu
        if (Schema::hasTable('group_members') && Schema::hasTable('group_messages')) {
            $maxIdsPerGroup = DB::table('group_messages')
                ->selectRaw('group_id, MAX(id) AS max_id')
                ->groupBy('group_id')
                ->pluck('max_id', 'group_id');

            foreach ($maxIdsPerGroup as $groupId => $maxId) {
                DB::table('group_members')
                    ->where('group_id', $groupId)
                    ->whereNull('last_read_message_id')
                    ->update(['last_read_message_id' => (int) $maxId]);
            }

            DB::table('group_members')
                ->whereNull('last_read_message_id')
                ->update(['last_read_message_id' => 0]);
        }

        // 2. Seed 4 Unit Layanan Kampus realistis ke tabel users
        if (Schema::hasTable('users')) {
            $roleId = DB::table('roles')->where('nama_role', 'Staff_Unit')->value('id')
                ?? DB::table('roles')->value('id')
                ?? 1;

            $serviceUnits = [
                [
                    'identifier' => 'unit_baak',
                    'name'       => 'Layanan Akademik / BAAK',
                    'unit'       => 'Biro Administrasi Akademik & Kemahasiswaan',
                ],
                [
                    'identifier' => 'unit_bau',
                    'name'       => 'Layanan Keuangan / BAU',
                    'unit'       => 'Biro Administrasi Umum & Keuangan',
                ],
                [
                    'identifier' => 'unit_ft',
                    'name'       => 'Tata Usaha Fakultas Teknik',
                    'unit'       => 'Tata Usaha Fakultas Teknik',
                ],
                [
                    'identifier' => 'unit_pdsi',
                    'name'       => 'Pusat Data & Sistem Informasi',
                    'unit'       => 'Pusat Data & Sistem Informasi (PDSI)',
                ],
            ];

            $hasIdentifierCol = Schema::hasColumn('users', 'identifier');
            $hasNameCol = Schema::hasColumn('users', 'name');
            $hasRoleNameCol = Schema::hasColumn('users', 'role_name');
            $hasUnitCol = Schema::hasColumn('users', 'unit');

            foreach ($serviceUnits as $su) {
                $existing = DB::table('users')
                    ->where('username', $su['identifier'])
                    ->when($hasIdentifierCol, fn ($q) => $q->orWhere('identifier', $su['identifier']))
                    ->first();

                $payload = [
                    'role_id'      => $roleId,
                    'nama_lengkap' => $su['name'],
                    'username'     => $su['identifier'],
                    'email_kampus' => $su['identifier'].'@unuja.ac.id',
                    'status_akun'  => 'Aktif',
                    'updated_at'   => now(),
                ];

                if ($hasIdentifierCol) {
                    $payload['identifier'] = $su['identifier'];
                }
                if ($hasNameCol) {
                    $payload['name'] = $su['name'];
                }
                if ($hasRoleNameCol) {
                    $payload['role_name'] = 'staff_unit';
                }
                if ($hasUnitCol) {
                    $payload['unit'] = $su['unit'];
                }

                if ($existing) {
                    DB::table('users')->where('id', $existing->id)->update($payload);
                } else {
                    $payload['password'] = Hash::make($su['identifier']);
                    $payload['created_at'] = now();
                    DB::table('users')->insert($payload);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'last_read_message_id')) {
            Schema::table('group_members', function (Blueprint $table): void {
                $table->dropColumn('last_read_message_id');
            });
        }
    }
};
