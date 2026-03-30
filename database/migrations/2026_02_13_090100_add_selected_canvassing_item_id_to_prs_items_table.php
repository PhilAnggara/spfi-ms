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
            $table->foreignId('selected_canvassing_item_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('prs_canvassing_items')
                ->onDelete(fk_on_delete('set null'));
            $table->text('selection_reason')
                ->nullable()
                ->after('selected_canvassing_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_items', function (Blueprint $table) {
            $table->dropColumn('selection_reason');
            $table->dropConstrainedForeignId('selected_canvassing_item_id');
        });
    }
};
