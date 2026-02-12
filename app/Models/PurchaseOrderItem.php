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
}
