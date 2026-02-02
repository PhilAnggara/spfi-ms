<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'desc',
    ];

    public function bsGroupings()
    {
        return $this->hasMany(BsGrouping::class, 'accounting_code_id');
    }

    public function groupCodes()
    {
        return $this->belongsToMany(
            AccountingGroupCode::class,
            'bs_groupings',
            'accounting_code_id',
            'group_code_id'
        )->withPivot('grouping_id', 'major')->withTimestamps();
    }
}
