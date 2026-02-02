<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingGroupCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_code',
        'group_desc',
    ];

    public function bsGroupings()
    {
        return $this->hasMany(BsGrouping::class, 'group_code_id');
    }

    public function accountingCodes()
    {
        return $this->belongsToMany(
            AccountingCode::class,
            'bs_groupings',
            'group_code_id',
            'accounting_code_id'
        )->withPivot('grouping_id', 'major')->withTimestamps();
    }
}
