<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FishSupplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fish_suppliers';
    protected $guarded = [
        'id'
    ];
}
