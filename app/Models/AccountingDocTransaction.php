<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingDocTransaction extends Model
{
    protected $fillable = [
        'doc_type',
        'source_type',
        'source_id',
        'doc_number',
        'doc_date',
        'po_number',
        'supplier_code',
        'supplier_name',
        'cost_code_total',
        'acct_code_total',
        'total_debit',
        'total_credit',
        'variance',
        'status',
        'legacy_tran_id',
        'encoded_by',
        'encoded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'source_id' => 'integer',
            'doc_date' => 'date',
            'cost_code_total' => 'decimal:4',
            'acct_code_total' => 'decimal:4',
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
            'variance' => 'decimal:4',
            'legacy_tran_id' => 'integer',
            'encoded_by' => 'integer',
            'encoded_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingDocTransactionLine::class)
            ->orderBy('line_no');
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isEncoded(): bool
    {
        return $this->status === 'encoded';
    }
}
