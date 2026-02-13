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
        Schema::create('prs_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prs_id')->constrained()->onDelete(fk_on_delete('cascade'));
            $table->foreignId('item_id')->constrained()->onDelete(fk_on_delete('restrict'));
            $table->foreignId('canvaser_id')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->integer('quantity');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prs_items');
    }
};
