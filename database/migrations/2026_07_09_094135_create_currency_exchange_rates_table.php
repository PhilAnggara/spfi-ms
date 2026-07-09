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
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->onDelete(fk_on_delete('restrict'));
            $table->decimal('rate_to_idr', 18, 4);
            $table->date('effective_date');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->onDelete(fk_on_delete('restrict'));
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->index(['currency_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
