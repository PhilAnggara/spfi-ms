<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'items';
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'id' => 'integer',
        'unit_of_measure_id' => 'integer',
        'category_id' => 'integer',
    ];
    protected $hidden = [

    ];

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id')->withTrashed();
    }
    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id')->withTrashed();
    }
}
