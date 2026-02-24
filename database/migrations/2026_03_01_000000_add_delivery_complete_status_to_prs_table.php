<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration enables DELIVERY_COMPLETE status for PRS
        // The status column already exists and can accept this new value
        // No schema changes needed, just for documentation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
