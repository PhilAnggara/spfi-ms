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
        Schema::table('store_withdrawal_items', function (Blueprint $table) {
            $table->foreignId('receiving_report_item_id')
                ->nullable()
                ->after('store_withdrawal_id')
                ->constrained('receiving_report_items')
                ->onDelete(fk_on_delete('restrict'));
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->after('receiving_report_item_id')
                ->constrained('purchase_order_items')
                ->onDelete(fk_on_delete('set null'));
            $table->foreignId('prs_item_id')
                ->nullable()
                ->after('purchase_order_item_id')
                ->constrained('prs_items')
                ->onDelete(fk_on_delete('set null'));

            $table->index('receiving_report_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_withdrawal_items', function (Blueprint $table) {
            $table->dropForeign(['receiving_report_item_id']);
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropForeign(['prs_item_id']);
            $table->dropColumn([
                'receiving_report_item_id',
                'purchase_order_item_id',
                'prs_item_id',
            ]);
        });
    }
};
