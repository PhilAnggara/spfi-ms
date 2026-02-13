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
        Schema::create('fish_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_id')->constrained('fish')->onDelete(fk_on_delete('cascade'));
            $table->string('code');
            $table->string('size_range');
            $table->foreignId('created_by')->constrained('users')->onDelete(fk_on_delete('restrict'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fish_id', 'code', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fish_sizes');
    }
};
