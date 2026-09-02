<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryLedger extends Model
{
    protected $table = 'accounting_inventory_ledger';

    protected $fillable = [
        'accounting_inventory_transaction_id',
        'accounting_inventory_transaction_line_id',
        'category_id',
        'item_id',
        'doc_type',
        'doc_number',
        'doc_date',
        'movement_date',
        'direction',
        'quantity',
        'unit_cost',
        'amount',
        'balance_qty',
        'balance_amount',
        'weighted_unit_cost',
        'is_reversal',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'accounting_inventory_transaction_id' => 'integer',
            'accounting_inventory_transaction_line_id' => 'integer',
            'category_id' => 'integer',
            'item_id' => 'integer',
            'quantity' => 'decimal:5',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:4',
            'balance_qty' => 'decimal:5',
            'balance_amount' => 'decimal:4',
            'weighted_unit_cost' => 'decimal:4',
            'is_reversal' => 'boolean',
            'created_by' => 'integer',
            'doc_date' => 'date',
            'movement_date' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryTransaction::class, 'accounting_inventory_transaction_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryTransactionLine::class, 'accounting_inventory_transaction_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }
}
