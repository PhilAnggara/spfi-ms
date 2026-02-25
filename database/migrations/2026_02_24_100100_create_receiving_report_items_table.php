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
        Schema::create('receiving_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_report_id')->constrained('receiving_reports')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->onDelete(fk_on_delete('restrict'));
            $table->decimal('qty_good', 15, 2)->default(0);
            $table->decimal('qty_bad', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['receiving_report_id', 'purchase_order_item_id'], 'rr_items_rrid_poid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_report_items');
    }
};
