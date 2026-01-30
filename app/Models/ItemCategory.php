<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'item_categories';
    protected $guarded = [
        'id'
    ];
    protected $hidden = [

    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
