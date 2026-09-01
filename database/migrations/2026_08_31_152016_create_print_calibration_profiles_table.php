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
        Schema::create('print_calibration_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 10);
            $table->string('name');
            $table->decimal('measured_anchor_x_mm', 5, 2);
            $table->decimal('measured_anchor_y_mm', 5, 2);
            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_calibration_profiles');
    }
};
