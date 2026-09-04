<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryMonthly extends Model
{
    protected $table = 'accounting_inventory_monthly';

    protected $fillable = [
        'legacy_monthly_id',
        'item_code',
        'doc_code',
        'doc_no',
        'qty',
        'u_cost',
        'begining',
        'ending',
        'tran_date',
        'category',
        'begining_u_cost',
        'item_id',
        'category_id',
        'source_type',
        'source_id',
        'supplier_id',
        'purchase_order_id',
        'accounting_inventory_doc_tran_id',
    ];

    protected function casts(): array
    {
        return [
            'legacy_monthly_id' => 'integer',
            'qty' => 'decimal:8',
            'u_cost' => 'decimal:8',
            'begining' => 'decimal:8',
            'ending' => 'decimal:8',
            'tran_date' => 'date',
            'begining_u_cost' => 'decimal:8',
            'item_id' => 'integer',
            'category_id' => 'integer',
            'source_id' => 'integer',
            'supplier_id' => 'integer',
            'purchase_order_id' => 'integer',
            'accounting_inventory_doc_tran_id' => 'integer',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function docTran(): BelongsTo
    {
        return $this->belongsTo(AccountingInventoryDocTran::class, 'accounting_inventory_doc_tran_id');
    }
}
