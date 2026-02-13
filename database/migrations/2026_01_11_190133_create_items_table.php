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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->string('code')->unique();
            $table->foreignId('unit_of_measure_id')->constrained('unit_of_measures')->onDelete(fk_on_delete('restrict'));
            $table->foreignId('category_id')->constrained('item_categories')->onDelete(fk_on_delete('restrict'));
            $table->string('type')->nullable();
            $table->integer('stock_on_hand')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
