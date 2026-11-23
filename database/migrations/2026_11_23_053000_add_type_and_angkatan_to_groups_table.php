<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table): void {
                if (! Schema::hasColumn('groups', 'type')) {
                    $table->string('type', 32)->default('kelas')->after('name');
                }
                if (! Schema::hasColumn('groups', 'angkatan')) {
                    $table->string('angkatan', 16)->nullable()->after('kode_tahun_akademik');
                }
            });

            DB::table('groups')
                ->whereNull('type')
                ->orWhere('type', '')
                ->update(['type' => 'kelas']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('groups')) {
            Schema::table('groups', function (Blueprint $table): void {
                if (Schema::hasColumn('groups', 'angkatan')) {
                    $table->dropColumn('angkatan');
                }
                if (Schema::hasColumn('groups', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};
