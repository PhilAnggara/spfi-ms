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
        Schema::create('prs', function (Blueprint $table) {
            $table->id();
            $table->string('prs_number')->unique();
            $table->foreignId('user_id')->constrained()->onDelete(fk_on_delete('cascade'));
            $table->foreignId('department_id')->constrained()->onDelete(fk_on_delete('restrict'));
            $table->date('prs_date');
            $table->date('date_needed');
            $table->text('remarks')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, RESUBMITTED, CANVASING, ON_HOLD, APPROVED
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prs');
    }
};
