<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingDocTransactionLine extends Model
{
    protected $fillable = [
        'accounting_doc_transaction_id',
        'line_no',
        'group_code',
        'account_code',
        'description',
        'debit',
        'credit',
        'legacy_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'accounting_doc_transaction_id' => 'integer',
            'line_no' => 'integer',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'legacy_detail_id' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(AccountingDocTransaction::class, 'accounting_doc_transaction_id');
    }

    /**
     * Cost center is only meaningful for 4-digit account codes (legacy GroupCode).
     */
    public function displayCostCenter(): ?string
    {
        $accountCode = trim((string) $this->account_code);
        if (! preg_match('/^\d{4}$/', $accountCode)) {
            return null;
        }

        $groupCode = trim((string) ($this->group_code ?? ''));

        return $groupCode !== '' ? $groupCode : null;
    }
}
