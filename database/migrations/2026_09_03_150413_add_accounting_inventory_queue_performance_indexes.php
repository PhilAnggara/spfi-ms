<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table): void {
            $table->index(['received_date'], 'rr_received_date_idx');
        });

        Schema::table('accounting_inventory_transactions', function (Blueprint $table): void {
            $table->index(
                ['source_type', 'source_id', 'category_id', 'doc_type', 'status'],
                'acct_inv_txn_source_cat_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table): void {
            $table->dropIndex('rr_received_date_idx');
        });

        Schema::table('accounting_inventory_transactions', function (Blueprint $table): void {
            $table->dropIndex('acct_inv_txn_source_cat_status_idx');
        });
    }
};
