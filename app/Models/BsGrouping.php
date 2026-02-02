<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BsGrouping extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_code_id',
        'accounting_code_id',
        'grouping_id',
        'major',
    ];

    public function groupCode()
    {
        return $this->belongsTo(AccountingGroupCode::class, 'group_code_id');
    }

    public function accountingCode()
    {
        return $this->belongsTo(AccountingCode::class, 'accounting_code_id');
    }

    public function grouping()
    {
        return $this->belongsTo(Grouping::class, 'grouping_id');
    }
}
