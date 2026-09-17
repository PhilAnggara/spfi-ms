<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropLegacyUniqueIndexes();

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('CREATE UNIQUE INDEX acct_inv_doc_tran_legacy_uid ON accounting_inventory_doc_tran (legacy_tran_id) WHERE legacy_tran_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX acct_inv_monthly_legacy_uid ON accounting_inventory_monthly (legacy_monthly_id) WHERE legacy_monthly_id IS NOT NULL');

            return;
        }

        Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
            $table->unique('legacy_tran_id', 'acct_inv_doc_tran_legacy_uid');
        });

        Schema::table('accounting_inventory_monthly', function (Blueprint $table): void {
            $table->unique('legacy_monthly_id', 'acct_inv_monthly_legacy_uid');
        });
    }

    public function down(): void
    {
        $this->dropLegacyUniqueIndexes();

        Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
            $table->unique('legacy_tran_id', 'acct_inv_doc_tran_legacy_uid');
        });

        Schema::table('accounting_inventory_monthly', function (Blueprint $table): void {
            $table->unique('legacy_monthly_id', 'acct_inv_monthly_legacy_uid');
        });
    }

    private function dropLegacyUniqueIndexes(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement('IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = \'acct_inv_doc_tran_legacy_uid\' AND object_id = OBJECT_ID(\'accounting_inventory_doc_tran\')) DROP INDEX acct_inv_doc_tran_legacy_uid ON accounting_inventory_doc_tran');
            DB::statement('IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = \'acct_inv_monthly_legacy_uid\' AND object_id = OBJECT_ID(\'accounting_inventory_monthly\')) DROP INDEX acct_inv_monthly_legacy_uid ON accounting_inventory_monthly');

            return;
        }

        Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
            $table->dropUnique('acct_inv_doc_tran_legacy_uid');
        });

        Schema::table('accounting_inventory_monthly', function (Blueprint $table): void {
            $table->dropUnique('acct_inv_monthly_legacy_uid');
        });
    }
};
