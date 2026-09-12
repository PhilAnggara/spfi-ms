<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('support_user_id')->nullable()->after('direct_key');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('support_user_id');
            $table->string('support_status', 20)->nullable()->after('assigned_to');
            $table->timestamp('support_last_read_at')->nullable()->after('support_status');

            $table->index(['type', 'support_status']);
            $table->index('assigned_to');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            // SQL Server treats multiple NULLs as duplicates in a UNIQUE index.
            DB::statement('CREATE UNIQUE INDEX conversations_support_user_id_unique ON conversations (support_user_id) WHERE support_user_id IS NOT NULL');
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                $table->unique('support_user_id');
            });
        }

        // SQL Server rejects multiple cascade paths to the same table.
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('support_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->noActionOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('persona', 20)->default('user')->after('user_id');
            $table->index('persona');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['support_user_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['type', 'support_status']);
            $table->dropIndex(['assigned_to']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('DROP INDEX conversations_support_user_id_unique ON conversations');
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropUnique(['support_user_id']);
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['support_user_id', 'assigned_to', 'support_status', 'support_last_read_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['persona']);
            $table->dropColumn('persona');
        });
    }
};
