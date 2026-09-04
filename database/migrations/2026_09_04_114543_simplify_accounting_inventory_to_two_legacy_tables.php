<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_inventory_doc_tran')) {
            $this->dropForeignKeyIfExists('accounting_inventory_doc_tran', 'acct_inv_doc_tran_line_fk');
            $this->dropForeignKeyIfExists('accounting_inventory_doc_tran', 'acct_inv_doc_tran_txn_fk');
            $this->dropForeignKeyIfExists(
                'accounting_inventory_doc_tran',
                'accounting_inventory_doc_tran_accounting_inventory_transaction_line_id_foreign',
            );
            $this->dropForeignKeyIfExists(
                'accounting_inventory_doc_tran',
                'accounting_inventory_doc_tran_accounting_inventory_transaction_id_foreign',
            );

            if (
                Schema::getConnection()->getDriverName() === 'sqlite'
                && (
                    Schema::hasColumn('accounting_inventory_doc_tran', 'accounting_inventory_transaction_id')
                    || Schema::hasColumn('accounting_inventory_doc_tran', 'accounting_inventory_transaction_line_id')
                )
            ) {
                DB::statement('PRAGMA foreign_keys=OFF');
                Schema::rename('accounting_inventory_doc_tran', 'accounting_inventory_doc_tran_old');
                Schema::create('accounting_inventory_doc_tran', function (Blueprint $table): void {
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
                DB::statement('INSERT INTO accounting_inventory_doc_tran (
                    id, legacy_tran_id, doc_code, doc_no, doc_date, po_no, item_code, qty, u_cost, uom,
                    ave_cost, t_qty, tran_date, input_time, modify_date, category, amount, item_id, category_id,
                    created_at, updated_at
                ) SELECT
                    id, legacy_tran_id, doc_code, doc_no, doc_date, po_no, item_code, qty, u_cost, uom,
                    ave_cost, t_qty, tran_date, input_time, modify_date, category, amount, item_id, category_id,
                    created_at, updated_at
                FROM accounting_inventory_doc_tran_old');
                Schema::drop('accounting_inventory_doc_tran_old');
                DB::statement('PRAGMA foreign_keys=ON');
            } elseif (Schema::getConnection()->getDriverName() !== 'sqlite') {
                Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
                    if (Schema::hasColumn('accounting_inventory_doc_tran', 'accounting_inventory_transaction_line_id')) {
                        $table->dropColumn('accounting_inventory_transaction_line_id');
                    }

                    if (Schema::hasColumn('accounting_inventory_doc_tran', 'accounting_inventory_transaction_id')) {
                        $table->dropColumn('accounting_inventory_transaction_id');
                    }
                });
            }

            Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'source_type')) {
                    $table->string('source_type')->nullable()->after('category_id');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('source_id')
                        ->constrained('suppliers')->onDelete(fk_on_delete('set null'));
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'purchase_order_id')) {
                    $table->foreignId('purchase_order_id')->nullable()->after('supplier_id')
                        ->constrained('purchase_orders')->onDelete(fk_on_delete('set null'));
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'party_code')) {
                    $table->string('party_code', 50)->nullable()->after('purchase_order_id');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'party_name')) {
                    $table->string('party_name', 191)->nullable()->after('party_code');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'remarks')) {
                    $table->text('remarks')->nullable()->after('party_name');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'is_corrected')) {
                    $table->boolean('is_corrected')->default(false)->after('remarks');
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'encoded_by')) {
                    $table->foreignId('encoded_by')->nullable()->after('is_corrected')
                        ->constrained('users')->onDelete(fk_on_delete('set null'));
                }

                if (! Schema::hasColumn('accounting_inventory_doc_tran', 'encoded_at')) {
                    $table->timestamp('encoded_at')->nullable()->after('encoded_by');
                }
            });

            Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
                $table->index(['doc_code', 'doc_no', 'category_id'], 'acct_inv_doc_tran_doc_cat_idx');
                $table->index(['source_type', 'source_id', 'category_id'], 'acct_inv_doc_tran_source_idx');
            });
        }

        if (Schema::hasTable('accounting_inventory_monthly')) {
            Schema::table('accounting_inventory_monthly', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_inventory_monthly', 'source_type')) {
                    $table->string('source_type')->nullable()->after('category_id');
                }

                if (! Schema::hasColumn('accounting_inventory_monthly', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }

                if (! Schema::hasColumn('accounting_inventory_monthly', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('source_id')
                        ->constrained('suppliers')->onDelete(fk_on_delete('set null'));
                }

                if (! Schema::hasColumn('accounting_inventory_monthly', 'purchase_order_id')) {
                    $table->foreignId('purchase_order_id')->nullable()->after('supplier_id')
                        ->constrained('purchase_orders')->onDelete(fk_on_delete('set null'));
                }
            });
        }

        Schema::dropIfExists('accounting_inventory_ledger');
        Schema::dropIfExists('accounting_inventory_transaction_lines');
        Schema::dropIfExists('accounting_inventory_transactions');
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_inventory_monthly')) {
            Schema::table('accounting_inventory_monthly', function (Blueprint $table): void {
                if (Schema::hasColumn('accounting_inventory_monthly', 'purchase_order_id')) {
                    $table->dropConstrainedForeignId('purchase_order_id');
                }
                if (Schema::hasColumn('accounting_inventory_monthly', 'supplier_id')) {
                    $table->dropConstrainedForeignId('supplier_id');
                }
                if (Schema::hasColumn('accounting_inventory_monthly', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('accounting_inventory_monthly', 'source_type')) {
                    $table->dropColumn('source_type');
                }
            });
        }

        if (Schema::hasTable('accounting_inventory_doc_tran')) {
            Schema::table('accounting_inventory_doc_tran', function (Blueprint $table): void {
                if (Schema::hasColumn('accounting_inventory_doc_tran', 'encoded_by')) {
                    $table->dropConstrainedForeignId('encoded_by');
                }
                if (Schema::hasColumn('accounting_inventory_doc_tran', 'purchase_order_id')) {
                    $table->dropConstrainedForeignId('purchase_order_id');
                }
                if (Schema::hasColumn('accounting_inventory_doc_tran', 'supplier_id')) {
                    $table->dropConstrainedForeignId('supplier_id');
                }

                foreach (['encoded_at', 'is_corrected', 'remarks', 'party_name', 'party_code', 'source_id', 'source_type'] as $column) {
                    if (Schema::hasColumn('accounting_inventory_doc_tran', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            $exists = DB::selectOne(
                'SELECT 1 AS ok FROM sys.foreign_keys WHERE name = ? AND parent_object_id = OBJECT_ID(?)',
                [$foreignKey, $table],
            );

            if ($exists) {
                DB::statement('ALTER TABLE ['.$table.'] DROP CONSTRAINT ['.$foreignKey.']');
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                $blueprint->dropForeign($foreignKey);
            });
        } catch (\Throwable) {
            // Constraint may not exist under this name on the current driver.
        }
    }
};
