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
        Schema::table('prs_canvasing_items', function (Blueprint $table) {
            $table->index('prs_item_id');
            $table->dropUnique(['prs_item_id']);
            $table->unique(['prs_item_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_canvasing_items', function (Blueprint $table) {
            $table->dropUnique(['prs_item_id', 'supplier_id']);
            $table->dropIndex(['prs_item_id']);
            $table->unique(['prs_item_id']);
        });
    }
};
