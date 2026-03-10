<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingReportItem extends Model
{
    use HasFactory;

    protected $table = 'receiving_report_items';

    protected $fillable = [
        'receiving_report_id',
        'purchase_order_item_id',
        'qty_good',
        'qty_bad',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'receiving_report_id' => 'integer',
        'purchase_order_item_id' => 'integer',
        'qty_good' => 'decimal:2',
        'qty_bad' => 'decimal:2',
        'meta' => 'array',
    ];

    public function receivingReport()
    {
        return $this->belongsTo(ReceivingReport::class, 'receiving_report_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
