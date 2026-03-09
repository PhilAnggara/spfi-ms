<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'prs_item_id',
        'item_id',
        'quantity',
        'unit_price',
        'line_subtotal',
        'discount_rate',
        'discount_amount',
        'ppn_rate',
        'ppn_amount',
        'pph_rate',
        'pph_amount',
        'total',
        'notes',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'purchase_order_id' => 'integer',
        'prs_item_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'ppn_rate' => 'decimal:2',
        'ppn_amount' => 'decimal:2',
        'pph_rate' => 'decimal:2',
        'pph_amount' => 'decimal:2',
        'total' => 'decimal:2',
        // Holds PR/canvasing snapshot (terms, lead time).
        'meta' => 'array',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function prsItem()
    {
        return $this->belongsTo(PrsItem::class, 'prs_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function receivingReportItems()
    {
        return $this->hasMany(ReceivingReportItem::class, 'purchase_order_item_id');
    }
}
