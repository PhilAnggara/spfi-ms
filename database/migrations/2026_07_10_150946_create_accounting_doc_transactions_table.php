<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_doc_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 10);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('doc_number', 50);
            $table->date('doc_date');
            $table->string('po_number', 50)->nullable();
            $table->string('supplier_code', 50)->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('cost_code_total', 18, 4)->default(0);
            $table->decimal('acct_code_total', 18, 4)->default(0);
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->decimal('variance', 18, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('legacy_tran_id')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamp('encoded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete(fk_on_delete('set null'));
            $table->timestamps();

            $table->unique(['doc_type', 'doc_number'], 'acct_doc_txn_doc_unique');
            $table->index(['doc_type', 'source_type', 'source_id'], 'acct_doc_txn_source_idx');
            $table->index(['doc_type', 'status', 'doc_date'], 'acct_doc_txn_list_idx');
            $table->index('legacy_tran_id', 'acct_doc_txn_legacy_idx');
        });

        Schema::create('accounting_doc_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_doc_transaction_id');
            $table->unsignedInteger('line_no');
            $table->string('group_code', 30)->nullable();
            $table->string('account_code', 20);
            $table->string('description')->nullable();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->unsignedBigInteger('legacy_detail_id')->nullable();
            $table->timestamps();

            $table->foreign('accounting_doc_transaction_id', 'acct_doc_txn_lines_txn_fk')
                ->references('id')
                ->on('accounting_doc_transactions')
                ->onDelete(fk_on_delete('cascade'));
            $table->index(['accounting_doc_transaction_id', 'line_no'], 'acct_doc_txn_lines_order_idx');
            $table->index('account_code', 'acct_doc_txn_lines_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_doc_transaction_lines');
        Schema::dropIfExists('accounting_doc_transactions');
    }
};
