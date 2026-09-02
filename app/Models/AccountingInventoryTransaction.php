<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingInventoryTransaction extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ENCODED = 'encoded';

    public const STATUS_VOIDED = 'voided';

    /**
     * @var list<string>
     */
    public const DOC_TYPES = ['RR', 'TS', 'DR', 'CV', 'JV'];

    /**
     * @var list<string>
     */
    public const MANUAL_DOC_TYPES = ['CV', 'JV'];

    protected $fillable = [
        'category_id',
        'doc_type',
        'doc_number',
        'doc_date',
        'po_number',
        'party_code',
        'party_name',
        'remarks',
        'status',
        'is_corrected',
        'total_amount',
        'source_type',
        'source_id',
        'gl_status',
        'accounting_doc_transaction_id',
        'encoded_by',
        'encoded_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category_id' => 'integer',
            'is_corrected' => 'boolean',
            'total_amount' => 'decimal:4',
            'source_id' => 'integer',
            'accounting_doc_transaction_id' => 'integer',
            'encoded_by' => 'integer',
            'voided_by' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'doc_date' => 'date',
            'encoded_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingInventoryTransactionLine::class, 'accounting_inventory_transaction_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AccountingInventoryLedger::class, 'accounting_inventory_transaction_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEncoded(): bool
    {
        return $this->status === self::STATUS_ENCODED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isManual(): bool
    {
        return in_array(strtoupper($this->doc_type), self::MANUAL_DOC_TYPES, true);
    }
}
