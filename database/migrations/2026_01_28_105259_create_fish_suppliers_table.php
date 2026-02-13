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
        Schema::create('fish_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('npwp')->nullable();
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
        Schema::dropIfExists('fish_suppliers');
    }
};
