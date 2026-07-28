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
            $table->decimal('quantity', 20, 5)->change();
        });

        Schema::table('receiving_report_items', function (Blueprint $table) {
            $table->decimal('qty_good', 20, 5)->default(0)->change();
            $table->decimal('qty_bad', 20, 5)->default(0)->change();
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('begin', 20, 5)->default(0)->change();
            $table->decimal('qty_in1', 20, 5)->default(0)->change();
            $table->decimal('qty_in2', 20, 5)->default(0)->change();
            $table->decimal('qty_in3', 20, 5)->default(0)->change();
            $table->decimal('qty_out1', 20, 5)->default(0)->change();
            $table->decimal('qty_out2', 20, 5)->default(0)->change();
            $table->decimal('qty_out3', 20, 5)->default(0)->change();
            $table->decimal('end', 20, 5)->default(0)->change();
            $table->decimal('acc_qty_in1', 20, 5)->default(0)->change();
            $table->decimal('acc_qty_total', 20, 5)->default(0)->change();
        });

        Schema::table('stock_inventories', function (Blueprint $table) {
            $table->decimal('balance', 20, 5)->default(0)->change();
            $table->decimal('start_balance', 20, 5)->default(0)->change();
        });

        Schema::table('store_withdrawal_items', function (Blueprint $table) {
            $table->decimal('quantity', 20, 5)->change();
            $table->decimal('stock_on_hand_snapshot', 20, 5)->default(0)->change();
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->decimal('quantity', 20, 5)->change();
        });

        Schema::table('transfer_slip_items', function (Blueprint $table) {
            $table->decimal('quantity', 20, 5)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prs_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('receiving_report_items', function (Blueprint $table) {
            $table->decimal('qty_good', 15, 2)->default(0)->change();
            $table->decimal('qty_bad', 15, 2)->default(0)->change();
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('begin', 15, 2)->default(0)->change();
            $table->decimal('qty_in1', 15, 2)->default(0)->change();
            $table->decimal('qty_in2', 15, 2)->default(0)->change();
            $table->decimal('qty_in3', 15, 2)->default(0)->change();
            $table->decimal('qty_out1', 15, 2)->default(0)->change();
            $table->decimal('qty_out2', 15, 2)->default(0)->change();
            $table->decimal('qty_out3', 15, 2)->default(0)->change();
            $table->decimal('end', 15, 2)->default(0)->change();
            $table->decimal('acc_qty_in1', 15, 2)->default(0)->change();
            $table->decimal('acc_qty_total', 15, 2)->default(0)->change();
        });

        Schema::table('stock_inventories', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->change();
            $table->decimal('start_balance', 15, 2)->default(0)->change();
        });

        Schema::table('store_withdrawal_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('stock_on_hand_snapshot', 15, 3)->default(0)->change();
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });

        Schema::table('transfer_slip_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });
    }
};
