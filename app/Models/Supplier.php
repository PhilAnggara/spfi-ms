<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'suppliers';
    protected $guarded = [
        'id'
    ];
    
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'fax',
        'email',
        'contact_person',
        'remarks',
        'term_of_payment_type',
        'term_of_payment',
        'term_of_delivery',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
    protected $hidden = [

    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
