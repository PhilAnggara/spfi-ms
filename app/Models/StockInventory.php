<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInventory extends Model
{
    use HasFactory;

    protected $table = 'stock_inventories';

    protected $fillable = [
        'item_id',
        'product_code',
        'wh_code',
        'balance',
        'start_balance',
        'average_price',
        'is_active',
        'is_delete',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'item_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'balance' => 'decimal:2',
        'start_balance' => 'decimal:2',
        'average_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_delete' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
