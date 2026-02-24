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
            $table->text('notes')->nullable();
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
