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
    protected $hidden = [

    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id', 'id');
    }
}
