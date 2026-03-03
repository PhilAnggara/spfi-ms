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
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('item_id')->constrained('items')->onDelete(fk_on_delete('restrict'));
            $table->string('product_code', 50);
            $table->string('wh_code', 20)->default('MAIN');
            $table->decimal('begin', 15, 2)->default(0);
            $table->decimal('qty_in1', 15, 2)->default(0);
            $table->decimal('qty_in2', 15, 2)->default(0);
            $table->decimal('qty_in3', 15, 2)->default(0);
            $table->decimal('qty_out1', 15, 2)->default(0);
            $table->decimal('qty_out2', 15, 2)->default(0);
            $table->decimal('qty_out3', 15, 2)->default(0);
            $table->decimal('end', 15, 2)->default(0);
            $table->decimal('acc_qty_in1', 15, 2)->default(0);
            $table->decimal('acc_average_price_in1', 15, 2)->default(0);
            $table->decimal('acc_qty_total', 15, 2)->default(0);
            $table->decimal('acc_average_price_total', 15, 2)->default(0);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('reference_line_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->index(['date', 'item_id'], 'stock_balances_date_item_index');
            $table->index(['product_code', 'wh_code'], 'stock_balances_product_wh_index');
            $table->index(['reference_type', 'reference_id'], 'stock_balances_reference_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
