<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes;

    protected $table = 'deliveries';

    protected $fillable = [
        'dr_number',
        'dr_date',
        'from_name',
        'from_location',
        'supplier_id',
        'to_location',
        'remarks',
        'or_number',
        'dm_number',
        'created_by',
        'updated_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'dr_date' => 'date',
            'supplier_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'meta' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function accountingDocTransaction(): MorphOne
    {
        return $this->morphOne(AccountingDocTransaction::class, 'source')
            ->where('doc_type', 'DR');
    }
}
