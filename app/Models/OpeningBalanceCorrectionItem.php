<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalanceCorrectionItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'opening_balance_correction_items';

    protected $fillable = [
        'opening_balance_correction_id',
        'item_id',
        'product_code',
        'wh_code',
        'previous_beginning',
        'new_beginning',
        'delta_qty',
        'replayed_movements',
        'created_by',
        'updated_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'opening_balance_correction_id' => 'integer',
        'item_id' => 'integer',
        'previous_beginning' => 'decimal:5',
        'new_beginning' => 'decimal:5',
        'delta_qty' => 'decimal:5',
        'replayed_movements' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'meta' => 'array',
    ];

    public function openingBalanceCorrection(): BelongsTo
    {
        return $this->belongsTo(OpeningBalanceCorrection::class, 'opening_balance_correction_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
