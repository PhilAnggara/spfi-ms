<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prs extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'prs';
    protected $guarded = [
        'id'
    ];
    protected $hidden = [

    ];

    public function items()
    {
        return $this->hasMany(PrsItem::class, 'prs_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
}
