<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FishSize;

class Fish extends Model
{
    use HasFactory;
    use SoftDeletes;

    // protected $table = 'fish';
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function sizes()
    {
        return $this->hasMany(FishSize::class);
    }
}
