<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustmentItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'stock_adjustment_items';

    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'product_code',
        'wh_code',
        'previous_balance',
        'new_balance',
        'delta_qty',
        'created_by',
        'updated_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'stock_adjustment_id' => 'integer',
        'item_id' => 'integer',
        'previous_balance' => 'decimal:5',
        'new_balance' => 'decimal:5',
        'delta_qty' => 'decimal:5',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'meta' => 'array',
    ];

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
