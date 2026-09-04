<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingInventoryDocTran extends Model
{
    protected $table = 'accounting_inventory_doc_tran';

    protected $fillable = [
        'legacy_tran_id',
        'doc_code',
        'doc_no',
        'doc_date',
        'po_no',
        'item_code',
        'qty',
        'u_cost',
        'uom',
        'ave_cost',
        't_qty',
        'tran_date',
        'input_time',
        'modify_date',
        'category',
        'amount',
        'item_id',
        'category_id',
        'accounting_inventory_transaction_id',
        'accounting_inventory_transaction_line_id',
    ];

    protected function casts(): array
    {
        return [
            'legacy_tran_id' => 'integer',
            'doc_date' => 'date',
            'qty' => 'decimal:5',
            'u_cost' => 'decimal:8',
            'ave_cost' => 'decimal:8',
            't_qty' => 'decimal:5',
            'tran_date' => 'date',
            'modify_date' => 'datetime',
            'amount' => 'decimal:4',
            'item_id' => 'integer',
            'category_id' => 'integer',
            'accounting_inventory_transaction_id' => 'integer',
            'accounting_inventory_transaction_line_id' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryTransaction::class, 'accounting_inventory_transaction_id');
    }

    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryTransactionLine::class, 'accounting_inventory_transaction_line_id');
    }

    public function monthlyRows(): HasMany
    {
        return $this->hasMany(AccountingInventoryMonthly::class, 'accounting_inventory_doc_tran_id');
    }

    public function isInbound(): bool
    {
        return (float) $this->qty > 0;
    }
}
