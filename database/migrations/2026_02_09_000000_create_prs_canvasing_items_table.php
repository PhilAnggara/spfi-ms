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
        Schema::create('prs_canvasing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prs_id')->constrained('prs')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('prs_item_id')->constrained('prs_items')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete(fk_on_delete('restrict'));
            $table->decimal('unit_price', 15, 2);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('term_of_payment_type')->nullable();
            $table->string('term_of_payment')->nullable();
            $table->string('term_of_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('canvased_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->unique('prs_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prs_canvasing_items');
    }
};
