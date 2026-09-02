<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryTransactionLine extends Model
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'accounting_inventory_transaction_id',
        'item_id',
        'direction',
        'quantity',
        'unit_of_measure_id',
        'unit_cost',
        'amount',
        'prefill_quantity',
        'prefill_unit_cost',
        'available_qty_snapshot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'accounting_inventory_transaction_id' => 'integer',
            'item_id' => 'integer',
            'unit_of_measure_id' => 'integer',
            'quantity' => 'decimal:5',
            'unit_cost' => 'decimal:4',
            'amount' => 'decimal:4',
            'prefill_quantity' => 'decimal:5',
            'prefill_unit_cost' => 'decimal:4',
            'available_qty_snapshot' => 'decimal:5',
            'sort_order' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryTransaction::class, 'accounting_inventory_transaction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function wasCorrected(): bool
    {
        if ($this->prefill_quantity === null && $this->prefill_unit_cost === null) {
            return false;
        }

        $qtyChanged = $this->prefill_quantity !== null
            && abs((float) $this->quantity - (float) $this->prefill_quantity) >= 0.00001;
        $costChanged = $this->prefill_unit_cost !== null
            && abs((float) $this->unit_cost - (float) $this->prefill_unit_cost) >= 0.0001;

        return $qtyChanged || $costChanged;
    }
}
