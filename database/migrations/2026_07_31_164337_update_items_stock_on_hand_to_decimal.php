<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 20, 5)->default(0)->change();
        });

        DB::table('items')->update(['stock_on_hand' => 0]);

        $totals = DB::table('stock_inventories')
            ->selectRaw('item_id, SUM(balance) AS total_balance')
            ->where('is_active', true)
            ->where('is_delete', false)
            ->groupBy('item_id')
            ->pluck('total_balance', 'item_id');

        foreach ($totals as $itemId => $totalBalance) {
            DB::table('items')
                ->where('id', (int) $itemId)
                ->update([
                    'stock_on_hand' => round((float) $totalBalance, 5),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $items = DB::table('items')->select(['id', 'stock_on_hand'])->get();

        foreach ($items as $item) {
            DB::table('items')
                ->where('id', $item->id)
                ->update([
                    'stock_on_hand' => (int) round((float) $item->stock_on_hand),
                ]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->integer('stock_on_hand')->default(0)->change();
        });
    }
};
