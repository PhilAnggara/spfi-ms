<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'batches';
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fish_supplier_id' => 'integer',
        'vessel_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function fishSupplier()
    {
        return $this->belongsTo(FishSupplier::class, 'fish_supplier_id', 'id');
    }
    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id', 'id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
