<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_correction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_correction_id');
            $table->foreign('opening_balance_correction_id', 'obc_items_header_foreign')
                ->references('id')
                ->on('opening_balance_corrections')
                ->onDelete(fk_on_delete('cascade'));
            $table->foreignId('item_id')->constrained('items')->onDelete(fk_on_delete('restrict'));
            $table->string('product_code', 100);
            $table->string('wh_code', 20)->default('MAIN');
            $table->decimal('previous_beginning', 15, 5)->default(0);
            $table->decimal('new_beginning', 15, 5)->default(0);
            $table->decimal('delta_qty', 15, 5)->default(0);
            $table->unsignedInteger('replayed_movements')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('opening_balance_correction_id', 'obc_items_header_index');
            $table->index(['item_id', 'wh_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_correction_items');
    }
};
