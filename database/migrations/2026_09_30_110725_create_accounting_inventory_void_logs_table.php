<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_inventory_void_logs', function (Blueprint $table) {
            $table->id();
            $table->string('doc_code', 10);
            $table->string('doc_no', 50);
            $table->unsignedBigInteger('category_id');
            $table->text('reason');
            $table->foreignId('voided_by')->constrained('users');
            $table->timestamp('voided_at');
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();

            $table->index(['doc_code', 'doc_no', 'category_id'], 'acct_inv_void_logs_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_inventory_void_logs');
    }
};
