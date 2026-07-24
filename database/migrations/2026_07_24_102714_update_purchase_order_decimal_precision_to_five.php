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
        Schema::table('prs_canvassing_items', function (Blueprint $table) {
            $table->decimal('unit_price', 20, 5)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 20, 5)->change();
            $table->decimal('unit_price', 20, 5)->change();
            $table->decimal('line_subtotal', 20, 5)->default(0)->change();
            $table->decimal('discount_rate', 10, 5)->default(0)->change();
            $table->decimal('discount_amount', 20, 5)->default(0)->change();
            $table->decimal('ppn_rate', 10, 5)->default(0)->change();
            $table->decimal('ppn_amount', 20, 5)->default(0)->change();
            $table->decimal('pph_rate', 10, 5)->default(0)->change();
            $table->decimal('pph_amount', 20, 5)->default(0)->change();
            $table->decimal('total', 20, 5)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 20, 5)->default(0)->change();
            $table->decimal('tax_rate', 10, 5)->default(0)->change();
            $table->decimal('tax_amount', 20, 5)->default(0)->change();
            $table->decimal('discount_rate', 10, 5)->default(0)->change();
            $table->decimal('discount_amount', 20, 5)->default(0)->change();
            $table->decimal('ppn_rate', 10, 5)->default(0)->change();
            $table->decimal('ppn_amount', 20, 5)->default(0)->change();
            $table->decimal('pph_rate', 10, 5)->default(0)->change();
            $table->decimal('pph_amount', 20, 5)->default(0)->change();
            $table->decimal('fees', 20, 5)->default(0)->change();
            $table->decimal('total', 20, 5)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_canvassing_items', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('line_subtotal', 15, 2)->default(0)->change();
            $table->decimal('discount_rate', 5, 2)->default(0)->change();
            $table->decimal('discount_amount', 15, 2)->default(0)->change();
            $table->decimal('ppn_rate', 5, 2)->default(0)->change();
            $table->decimal('ppn_amount', 15, 2)->default(0)->change();
            $table->decimal('pph_rate', 5, 2)->default(0)->change();
            $table->decimal('pph_amount', 15, 2)->default(0)->change();
            $table->decimal('total', 15, 2)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0)->change();
            $table->decimal('tax_rate', 5, 2)->default(0)->change();
            $table->decimal('tax_amount', 15, 2)->default(0)->change();
            $table->decimal('discount_rate', 5, 2)->default(0)->change();
            $table->decimal('discount_amount', 15, 2)->default(0)->change();
            $table->decimal('ppn_rate', 5, 2)->default(0)->change();
            $table->decimal('ppn_amount', 15, 2)->default(0)->change();
            $table->decimal('pph_rate', 5, 2)->default(0)->change();
            $table->decimal('pph_amount', 15, 2)->default(0)->change();
            $table->decimal('fees', 15, 2)->default(0)->change();
            $table->decimal('total', 15, 2)->default(0)->change();
        });
    }
};
