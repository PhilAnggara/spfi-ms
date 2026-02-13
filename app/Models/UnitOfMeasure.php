<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unit_of_measures';
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'unit_of_measure_id');
    }
}
