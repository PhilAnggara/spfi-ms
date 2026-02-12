<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrsItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'prs_items';
    protected $guarded = [
        'id'
    ];
    protected $hidden = [

    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function prs()
    {
        return $this->belongsTo(Prs::class, 'prs_id', 'id');
    }

    public function canvasingItems()
    {
        return $this->hasMany(PrsCanvasingItem::class, 'prs_item_id', 'id');
    }

    public function selectedCanvasingItem()
    {
        return $this->belongsTo(PrsCanvasingItem::class, 'selected_canvasing_item_id');
    }

    public function canvaser()
    {
        return $this->belongsTo(User::class, 'canvaser_id', 'id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderItem()
    {
        return $this->hasOne(PurchaseOrderItem::class, 'prs_item_id', 'id');
    }
}
