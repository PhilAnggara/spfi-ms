<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('obc_number')->unique();
            $table->date('period_month');
            $table->text('reason');
            $table->boolean('allow_negative_balance')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('period_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_corrections');
    }
};
