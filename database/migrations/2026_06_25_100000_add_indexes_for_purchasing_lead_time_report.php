<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table) {
            $table->index('created_at', 'receiving_reports_created_at_index');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index('prs_item_id', 'purchase_order_items_prs_item_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table) {
            $table->dropIndex('receiving_reports_created_at_index');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('purchase_order_items_prs_item_id_index');
        });
    }
};
