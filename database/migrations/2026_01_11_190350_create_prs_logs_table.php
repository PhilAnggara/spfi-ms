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
        Schema::create('prs_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prs_id')->constrained('prs')->onDelete(fk_on_delete('cascade'));
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->string('action', 50); // e.g. HOLD, RESUBMIT, COMMENT, APPROVE
            $table->text('message')->nullable(); // alasan / komentar
            $table->json('meta')->nullable(); // tambahan (prev_status, fields changed, etc.)
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prs_logs');
    }
};
