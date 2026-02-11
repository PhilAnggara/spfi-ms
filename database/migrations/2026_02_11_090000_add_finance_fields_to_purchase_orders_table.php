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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('ppn_rate', 5, 2)->default(0);
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('pph_rate', 5, 2)->default(0);
            $table->decimal('pph_amount', 15, 2)->default(0);
            $table->string('remark_type')->nullable();
            $table->string('remark_text')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'discount_rate',
                'discount_amount',
                'ppn_rate',
                'ppn_amount',
                'pph_rate',
                'pph_amount',
                'remark_type',
                'remark_text',
            ]);
            $table->dropConstrainedForeignId('currency_id');
        });
    }
};
