<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('item_categories', indexName: 'acct_inv_txn_category_fk')->onDelete(fk_on_delete('restrict'));
            $table->string('doc_type', 10);
            $table->string('doc_number', 50);
            $table->date('doc_date');
            $table->string('po_number', 50)->nullable();
            $table->string('party_code', 50)->nullable();
            $table->string('party_name')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_corrected')->default(false);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('gl_status', 20)->default('not_required');
            $table->foreignId('accounting_doc_transaction_id')
                ->nullable()
                ->constrained('accounting_doc_transactions', indexName: 'acct_inv_txn_doc_tran_fk')
                ->onDelete(fk_on_delete('set null'));
            $table->foreignId('encoded_by')->nullable()->constrained('users', indexName: 'acct_inv_txn_encoded_by_fk')->onDelete(fk_on_delete('set null'));
            $table->timestamp('encoded_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users', indexName: 'acct_inv_txn_voided_by_fk')->onDelete(fk_on_delete('set null'));
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_inv_txn_created_by_fk')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'acct_inv_txn_updated_by_fk')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->unique(['doc_type', 'doc_number'], 'acct_inv_txn_doc_unique');
            $table->index(['category_id', 'doc_date'], 'acct_inv_txn_category_date_idx');
            $table->index(['doc_type', 'status', 'doc_date'], 'acct_inv_txn_list_idx');
            $table->index(['source_type', 'source_id'], 'acct_inv_txn_source_idx');
            $table->index('gl_status', 'acct_inv_txn_gl_status_idx');
        });

        Schema::create('accounting_inventory_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_inventory_transaction_id')
                ->constrained('accounting_inventory_transactions', indexName: 'acct_inv_line_txn_fk')
                ->onDelete(fk_on_delete('cascade'));
            $table->foreignId('item_id')->constrained('items', indexName: 'acct_inv_line_item_fk')->onDelete(fk_on_delete('restrict'));
            $table->string('direction', 3);
            $table->decimal('quantity', 15, 5)->default(0);
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('unit_of_measures', indexName: 'acct_inv_line_uom_fk')->onDelete(fk_on_delete('set null'));
            $table->decimal('unit_cost', 18, 5)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('prefill_quantity', 15, 5)->nullable();
            $table->decimal('prefill_unit_cost', 18, 5)->nullable();
            $table->decimal('available_qty_snapshot', 15, 5)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['accounting_inventory_transaction_id', 'sort_order'], 'acct_inv_line_order_idx');
        });

        Schema::create('accounting_inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_inventory_transaction_id')
                ->constrained('accounting_inventory_transactions', indexName: 'acct_inv_ledger_txn_fk')
                ->onDelete(fk_on_delete('restrict'));
            $table->foreignId('accounting_inventory_transaction_line_id')
                ->constrained('accounting_inventory_transaction_lines', indexName: 'acct_inv_ledger_line_fk')
                ->onDelete(fk_on_delete('restrict'));
            $table->foreignId('category_id')->constrained('item_categories', indexName: 'acct_inv_ledger_category_fk')->onDelete(fk_on_delete('restrict'));
            $table->foreignId('item_id')->constrained('items', indexName: 'acct_inv_ledger_item_fk')->onDelete(fk_on_delete('restrict'));
            $table->string('doc_type', 10);
            $table->string('doc_number', 50);
            $table->date('doc_date');
            $table->date('movement_date');
            $table->string('direction', 3);
            $table->decimal('quantity', 15, 5)->default(0);
            $table->decimal('unit_cost', 18, 5)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('balance_qty', 15, 5)->default(0);
            $table->decimal('balance_amount', 18, 4)->default(0);
            $table->decimal('weighted_unit_cost', 18, 5)->default(0);
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'acct_inv_ledger_created_by_fk')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->index(['category_id', 'item_id', 'movement_date'], 'acct_inv_ledger_cat_item_date_idx');
            $table->index(['doc_type', 'doc_number'], 'acct_inv_ledger_doc_idx');
            $table->index(['doc_date', 'category_id'], 'acct_inv_ledger_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_inventory_ledger');
        Schema::dropIfExists('accounting_inventory_transaction_lines');
        Schema::dropIfExists('accounting_inventory_transactions');
    }
};
