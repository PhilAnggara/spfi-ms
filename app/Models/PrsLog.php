<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrsLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prs_logs';

    protected $fillable = [
        'prs_id',
        'user_id',
        'action',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function prs()
    {
        return $this->belongsTo(Prs::class, 'prs_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
