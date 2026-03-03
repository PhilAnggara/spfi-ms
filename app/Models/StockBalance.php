<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    use HasFactory;

    protected $table = 'stock_balances';

    protected $fillable = [
        'date',
        'item_id',
        'product_code',
        'wh_code',
        'begin',
        'qty_in1',
        'qty_in2',
        'qty_in3',
        'qty_out1',
        'qty_out2',
        'qty_out3',
        'end',
        'acc_qty_in1',
        'acc_average_price_in1',
        'acc_qty_total',
        'acc_average_price_total',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'created_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'item_id' => 'integer',
        'reference_id' => 'integer',
        'reference_line_id' => 'integer',
        'created_by' => 'integer',
        'date' => 'date',
        'begin' => 'decimal:2',
        'qty_in1' => 'decimal:2',
        'qty_in2' => 'decimal:2',
        'qty_in3' => 'decimal:2',
        'qty_out1' => 'decimal:2',
        'qty_out2' => 'decimal:2',
        'qty_out3' => 'decimal:2',
        'end' => 'decimal:2',
        'acc_qty_in1' => 'decimal:2',
        'acc_average_price_in1' => 'decimal:2',
        'acc_qty_total' => 'decimal:2',
        'acc_average_price_total' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
