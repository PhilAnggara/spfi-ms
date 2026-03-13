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
        Schema::create('transfer_slips', function (Blueprint $table) {
            $table->id();
            $table->string('ts_number')->unique(); // legacy: ts_code
            $table->date('ts_date'); // legacy: ts_date
            $table->foreignId('store_withdrawal_id')->constrained('store_withdrawals')->onDelete(fk_on_delete('restrict'));
            $table->boolean('for_production')->default(false);
            $table->text('remarks')->nullable(); // legacy: ts_info
            $table->string('transfer_to', 120)->nullable(); // legacy: ts_to
            $table->foreignId('noted_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamp('noted_at')->nullable(); // legacy: noted_date
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamp('approved_at')->nullable(); // legacy: approved_date
            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamp('received_at')->nullable(); // legacy: received_date
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ts_date', 'store_withdrawal_id']);
            $table->index('for_production');
        });

        Schema::create('transfer_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_slip_id')->constrained('transfer_slips')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('store_withdrawal_item_id')->nullable()->constrained('store_withdrawal_items')->onDelete(fk_on_delete('set null'));
            $table->foreignId('item_id')->constrained('items')->onDelete(fk_on_delete('restrict'));
            $table->string('product_code', 100)->nullable()->index();
            $table->decimal('quantity', 15, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transfer_slip_id');
            $table->index('store_withdrawal_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_slip_items');
        Schema::dropIfExists('transfer_slips');
    }
};
