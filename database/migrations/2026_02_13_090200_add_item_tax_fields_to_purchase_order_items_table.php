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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('line_subtotal', 15, 2)->default(0)->after('unit_price');
            $table->decimal('discount_rate', 5, 2)->default(0)->after('line_subtotal');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate');
            $table->decimal('ppn_rate', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('ppn_amount', 15, 2)->default(0)->after('ppn_rate');
            $table->decimal('pph_rate', 5, 2)->default(0)->after('ppn_amount');
            $table->decimal('pph_amount', 15, 2)->default(0)->after('pph_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'line_subtotal',
                'discount_rate',
                'discount_amount',
                'ppn_rate',
                'ppn_amount',
                'pph_rate',
                'pph_amount',
            ]);
        });
    }
};
