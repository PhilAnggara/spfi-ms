<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferSlipItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'transfer_slip_items';

    protected $fillable = [
        'transfer_slip_id',
        'store_withdrawal_item_id',
        'item_id',
        'product_code',
        'quantity',
        'created_by',
        'updated_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'transfer_slip_id' => 'integer',
        'store_withdrawal_item_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'decimal:3',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'meta' => 'array',
    ];

    public function transferSlip()
    {
        return $this->belongsTo(TransferSlip::class, 'transfer_slip_id');
    }
}
