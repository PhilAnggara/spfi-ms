<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_inventory_doc_tran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_tran_id')->nullable()->unique('acct_inv_doc_tran_legacy_uid');
            $table->string('doc_code', 10);
            $table->string('doc_no', 50);
            $table->date('doc_date');
            $table->string('po_no', 50)->nullable();
            $table->string('item_code', 30);
            $table->decimal('qty', 18, 5)->default(0);
            $table->decimal('u_cost', 24, 8)->default(0);
            $table->string('uom', 20)->nullable();
            $table->decimal('ave_cost', 24, 8)->nullable();
            $table->decimal('t_qty', 18, 5)->nullable();
            $table->date('tran_date');
            $table->time('input_time')->nullable();
            $table->dateTime('modify_date')->nullable();
            $table->string('category', 60);
            $table->decimal('amount', 21, 4)->default(0);
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete(fk_on_delete('set null'));
            $table->foreignId('category_id')->nullable()->constrained('item_categories')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->index(['tran_date', 'category'], 'acct_inv_doc_tran_date_cat_idx');
            $table->index(['doc_code', 'doc_no'], 'acct_inv_doc_tran_doc_idx');
            $table->index(['item_code', 'category'], 'acct_inv_doc_tran_item_cat_idx');
            $table->index(['category_id', 'item_id', 'tran_date'], 'acct_inv_doc_tran_fk_date_idx');
        });

        Schema::create('accounting_inventory_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_monthly_id')->nullable()->unique('acct_inv_monthly_legacy_uid');
            $table->string('item_code', 30);
            $table->string('doc_code', 10);
            $table->string('doc_no', 50);
            $table->decimal('qty', 24, 8)->default(0);
            $table->decimal('u_cost', 24, 8)->default(0);
            $table->decimal('begining', 24, 8)->default(0);
            $table->decimal('ending', 24, 8)->default(0);
            $table->date('tran_date');
            $table->string('category', 60);
            $table->decimal('begining_u_cost', 24, 8)->nullable();
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete(fk_on_delete('set null'));
            $table->foreignId('category_id')->nullable()->constrained('item_categories')->onDelete(fk_on_delete('set null'));
            $table->foreignId('accounting_inventory_doc_tran_id')
                ->nullable()
                ->constrained('accounting_inventory_doc_tran', indexName: 'acct_inv_monthly_doc_tran_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->index(['tran_date', 'category'], 'acct_inv_monthly_date_cat_idx');
            $table->index(['item_code', 'category', 'tran_date'], 'acct_inv_monthly_item_idx');
            $table->index(['accounting_inventory_doc_tran_id'], 'acct_inv_monthly_doc_tran_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_inventory_monthly');
        Schema::dropIfExists('accounting_inventory_doc_tran');
    }
};
