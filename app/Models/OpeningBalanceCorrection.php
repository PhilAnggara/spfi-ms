<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalanceCorrection extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'opening_balance_corrections';

    protected $fillable = [
        'obc_number',
        'period_month',
        'reason',
        'allow_negative_balance',
        'created_by',
        'updated_by',
        'reversed_by',
        'reversed_at',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'reversed_by' => 'integer',
        'period_month' => 'date',
        'allow_negative_balance' => 'boolean',
        'reversed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OpeningBalanceCorrectionItem::class, 'opening_balance_correction_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
