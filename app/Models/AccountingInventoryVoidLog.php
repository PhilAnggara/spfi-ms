<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingInventoryVoidLog extends Model
{
    protected $table = 'accounting_inventory_void_logs';

    protected $fillable = [
        'doc_code',
        'doc_no',
        'category_id',
        'reason',
        'voided_by',
        'voided_at',
        'payload_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'voided_by' => 'integer',
            'voided_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }
}
