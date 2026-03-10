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
        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id();
            $table->string('rr_number')->nullable()->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete(fk_on_delete('restrict'));
            $table->date('received_date');
            $table->boolean('requires_customs_document')->default(false);
            $table->string('customs_document_number')->nullable()->index();
            $table->unsignedBigInteger('customs_document_type_id')->nullable()->index();
            $table->date('customs_document_date')->nullable();
            $table->text('notes')->nullable();
            // Keep non-core legacy attributes without affecting new workflow columns.
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete(fk_on_delete('restrict'));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_reports');
    }
};
