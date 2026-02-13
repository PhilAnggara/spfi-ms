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
        Schema::table('prs_items', function (Blueprint $table) {
            // Prevent reusing PR items once a PO is created.
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('canvaser_id')
                ->constrained('purchase_orders')
                ->onDelete(fk_on_delete('set null'));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });
    }
};
