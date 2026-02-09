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
        'notes',
        'canvased_by',
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
