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
        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'reply_to_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('group_id');
                $table->foreign('reply_to_id')->references('id')->on('messages')->onDelete('set null');
            });
        }

        if (Schema::hasTable('group_messages') && ! Schema::hasColumn('group_messages', 'reply_to_id')) {
            Schema::table('group_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('group_id');
                $table->foreign('reply_to_id')->references('id')->on('group_messages')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'reply_to_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropForeign(['reply_to_id']);
                $table->dropColumn('reply_to_id');
            });
        }

        if (Schema::hasTable('group_messages') && Schema::hasColumn('group_messages', 'reply_to_id')) {
            Schema::table('group_messages', function (Blueprint $table): void {
                $table->dropForeign(['reply_to_id']);
                $table->dropColumn('reply_to_id');
            });
        }
    }
};
