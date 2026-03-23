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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('dr_number')->unique(); // legacy: dr_code
            $table->date('dr_date'); // legacy: dr_date
            $table->string('from_name', 120)->default('IM - PT. SPFI'); // legacy: dr_from
            $table->string('from_location', 120)->nullable(); // legacy: dr_fromloc
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete(fk_on_delete('set null')); // legacy: supplier_code -> suppliers.id
            $table->string('to_location', 120)->nullable(); // legacy: dr_toloc
            $table->text('remarks')->nullable(); // legacy: dr_remarks
            $table->string('or_number', 80)->nullable(); // legacy: or_code
            $table->string('dm_number', 80)->nullable(); // legacy: dm_code
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dr_date');
            $table->index('from_location');
            $table->index('supplier_id');
            $table->index('to_location');
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete(fk_on_delete('set null'));
            $table->string('product_code', 100)->nullable()->index(); // legacy: product_code
            $table->string('uom', 50)->nullable(); // legacy: dr_uom
            $table->decimal('quantity', 15, 3); // legacy: dr_qty
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('delivery_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
