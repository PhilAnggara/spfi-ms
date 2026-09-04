<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
        'source_type',
        'source_id',
        'supplier_id',
        'purchase_order_id',
        'party_code',
        'party_name',
        'remarks',
        'is_corrected',
        'encoded_by',
        'encoded_at',
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
            'source_id' => 'integer',
            'supplier_id' => 'integer',
            'purchase_order_id' => 'integer',
            'is_corrected' => 'boolean',
            'encoded_by' => 'integer',
            'encoded_at' => 'datetime',
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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
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
