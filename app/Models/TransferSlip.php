<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferSlip extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'transfer_slips';

    protected $fillable = [
        'ts_number',
        'ts_date',
        'store_withdrawal_id',
        'for_production',
        'remarks',
        'transfer_to',
        'noted_by',
        'noted_at',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at',
        'created_by',
        'updated_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'store_withdrawal_id' => 'integer',
        'for_production' => 'boolean',
        'noted_by' => 'integer',
        'approved_by' => 'integer',
        'received_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'ts_date' => 'date',
        'noted_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'meta' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(TransferSlipItem::class, 'transfer_slip_id');
    }
}
