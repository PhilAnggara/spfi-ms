<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Backfill support threads so they never collide on NULL direct_key.
        DB::table('conversations')
            ->where('type', 'support')
            ->whereNull('direct_key')
            ->orderBy('id')
            ->get(['id', 'support_user_id'])
            ->each(function (object $row): void {
                if (! $row->support_user_id) {
                    return;
                }

                DB::table('conversations')
                    ->where('id', $row->id)
                    ->update([
                        'direct_key' => hash('sha256', 'support:'.$row->support_user_id),
                    ]);
            });

        if ($driver === 'sqlsrv') {
            // SQL Server UNIQUE indexes treat multiple NULLs as duplicates.
            DB::statement('DROP INDEX conversations_direct_key_unique ON conversations');
            DB::statement('CREATE UNIQUE INDEX conversations_direct_key_unique ON conversations (direct_key) WHERE direct_key IS NOT NULL');

            return;
        }

        // Other drivers already allow multiple NULLs in UNIQUE columns.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX conversations_direct_key_unique ON conversations');

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique('direct_key');
        });
    }
};
