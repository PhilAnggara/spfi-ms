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
        Schema::create('stock_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->onDelete(fk_on_delete('restrict'));
            $table->string('product_code', 50);
            $table->string('wh_code', 20)->default('MAIN');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('start_balance', 15, 2)->default(0);
            $table->decimal('average_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_delete')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->unique(['item_id', 'wh_code'], 'stock_inventories_item_wh_unique');
            $table->index(['product_code', 'wh_code'], 'stock_inventories_product_wh_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_inventories');
    }
};
