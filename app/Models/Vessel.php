<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vessel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vessels';
    protected $guarded = [
        'id'
    ];

    /**
     * Get the user who created this fish supplier.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this fish supplier.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
