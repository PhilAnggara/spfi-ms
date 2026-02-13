<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grouping extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'desc',
        'major',
        'grp',
        'tab',
        'other',
        'selection',
    ];

    protected $casts = [
        'id' => 'integer',
        'other' => 'boolean',
        'selection' => 'boolean',
    ];

    public function bsGroupings()
    {
        return $this->hasMany(BsGrouping::class, 'grouping_id');
    }
}
