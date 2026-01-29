<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishSize extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fish_sizes';
    protected $guarded = [
        'id',
    ];

    public function fish()
    {
        return $this->belongsTo(Fish::class);
    }
}
