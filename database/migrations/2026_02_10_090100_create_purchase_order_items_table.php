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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('prs_item_id')->nullable()->constrained('prs_items')->onDelete(fk_on_delete('set null'));
            $table->foreignId('item_id')->constrained('items')->onDelete(fk_on_delete('restrict'));
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->text('notes')->nullable();
            // Snapshot PR/canvasing details for audit.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'item_id'], 'purchase_order_items_po_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
