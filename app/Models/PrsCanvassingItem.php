<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrsCanvassingItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prs_canvassing_items';

    protected $fillable = [
        'prs_id',
        'prs_item_id',
        'supplier_id',
        'is_selected',
        'unit_price',
        'lead_time_days',
        'term_of_payment_type',
        'term_of_payment',
        'term_of_delivery',
        'notes',
        'canvased_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'prs_id' => 'integer',
        'prs_item_id' => 'integer',
        'supplier_id' => 'integer',
        'is_selected' => 'boolean',
        'unit_price' => 'decimal:5',
        'lead_time_days' => 'integer',
        'canvased_by' => 'integer',
        'meta' => 'array',
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
