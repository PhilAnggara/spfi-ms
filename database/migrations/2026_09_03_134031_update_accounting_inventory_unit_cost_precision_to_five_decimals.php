<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('accounting_inventory_transaction_lines') || ! Schema::hasTable('accounting_inventory_ledger')) {
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE accounting_inventory_transaction_lines ALTER COLUMN unit_cost DECIMAL(18, 5) NOT NULL');
            DB::statement('ALTER TABLE accounting_inventory_transaction_lines ALTER COLUMN prefill_unit_cost DECIMAL(18, 5) NULL');
            DB::statement('ALTER TABLE accounting_inventory_ledger ALTER COLUMN unit_cost DECIMAL(18, 5) NOT NULL');
            DB::statement('ALTER TABLE accounting_inventory_ledger ALTER COLUMN weighted_unit_cost DECIMAL(18, 5) NOT NULL');

            return;
        }

        DB::statement('ALTER TABLE accounting_inventory_transaction_lines MODIFY unit_cost DECIMAL(18, 5) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE accounting_inventory_transaction_lines MODIFY prefill_unit_cost DECIMAL(18, 5) NULL');
        DB::statement('ALTER TABLE accounting_inventory_ledger MODIFY unit_cost DECIMAL(18, 5) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE accounting_inventory_ledger MODIFY weighted_unit_cost DECIMAL(18, 5) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE accounting_inventory_transaction_lines ALTER COLUMN unit_cost DECIMAL(18, 4) NOT NULL');
            DB::statement('ALTER TABLE accounting_inventory_transaction_lines ALTER COLUMN prefill_unit_cost DECIMAL(18, 4) NULL');
            DB::statement('ALTER TABLE accounting_inventory_ledger ALTER COLUMN unit_cost DECIMAL(18, 4) NOT NULL');
            DB::statement('ALTER TABLE accounting_inventory_ledger ALTER COLUMN weighted_unit_cost DECIMAL(18, 4) NOT NULL');

            return;
        }

        DB::statement('ALTER TABLE accounting_inventory_transaction_lines MODIFY unit_cost DECIMAL(18, 4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE accounting_inventory_transaction_lines MODIFY prefill_unit_cost DECIMAL(18, 4) NULL');
        DB::statement('ALTER TABLE accounting_inventory_ledger MODIFY unit_cost DECIMAL(18, 4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE accounting_inventory_ledger MODIFY weighted_unit_cost DECIMAL(18, 4) NOT NULL DEFAULT 0');
    }
};
