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
        Schema::create('store_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('sws_number')->unique(); // legacy: sws_code
            $table->date('sws_date'); // legacy: sws_date
            $table->foreignId('department_id')->constrained('departments')->onDelete(fk_on_delete('restrict'));
            $table->string('department_code', 30)->index(); // snapshot for legacy mapping
            $table->string('type', 20)->default('normal'); // normal, confirmatory
            $table->text('info')->nullable(); // legacy: sws_info
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sws_date', 'department_id']);
            $table->index('type');
        });

        Schema::create('store_withdrawal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_withdrawal_id')->constrained('store_withdrawals')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete(fk_on_delete('set null'));
            $table->string('product_code', 100)->nullable()->index(); // legacy: product_code
            $table->decimal('quantity', 15, 3); // legacy: qty
            $table->decimal('stock_on_hand_snapshot', 15, 3)->default(0); // legacy: soh
            $table->string('uom', 50)->nullable(); // legacy: uom
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_withdrawal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_withdrawal_items');
        Schema::dropIfExists('store_withdrawals');
    }
};
