<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fish extends Model
{
    use HasFactory;
    use SoftDeletes;

    // protected $table = 'fish';
    protected $guarded = [
        'id'
    ];
}
