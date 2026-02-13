<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrsCanvasingItem extends Model
{
    use HasFactory;

    protected $table = 'prs_canvasing_items';

    protected $fillable = [
        'prs_id',
        'prs_item_id',
        'supplier_id',
        'unit_price',
        'lead_time_days',
        'term_of_payment_type',
        'term_of_payment',
        'term_of_delivery',
        'notes',
        'canvased_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'prs_id' => 'integer',
        'prs_item_id' => 'integer',
        'supplier_id' => 'integer',
        'lead_time_days' => 'integer',
        'canvased_by' => 'integer',
    ];

    public function prs()
    {
        return $this->belongsTo(Prs::class, 'prs_id');
    }

    public function prsItem()
    {
        return $this->belongsTo(PrsItem::class, 'prs_item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function canvasedBy()
    {
        return $this->belongsTo(User::class, 'canvased_by');
    }
}
