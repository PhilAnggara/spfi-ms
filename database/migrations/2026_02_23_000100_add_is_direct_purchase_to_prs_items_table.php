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
        Schema::table('prs_items', function (Blueprint $table) {
            $table->boolean('is_direct_purchase')
                ->default(false)
                ->after('selection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_items', function (Blueprint $table) {
            $table->dropColumn('is_direct_purchase');
        });
    }
};
