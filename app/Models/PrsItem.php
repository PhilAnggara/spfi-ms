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

    public function canvasingItem()
    {
        return $this->hasOne(PrsCanvasingItem::class, 'prs_item_id', 'id');
    }

    public function canvaser()
    {
        return $this->belongsTo(User::class, 'canvaser_id', 'id');
    }
}
