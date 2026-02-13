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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('fish_supplier_id')->constrained()->onDelete(fk_on_delete('restrict'));
            $table->foreignId('vessel_id')->constrained()->onDelete(fk_on_delete('restrict'));
            $table->string('fishing_method');
            $table->string('fish_type');
            $table->foreignId('created_by')->constrained('users')->onDelete(fk_on_delete('restrict'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
