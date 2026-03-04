<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomsDocumentType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'customs_document_types';

    protected $fillable = [
        'name',
        'code',
        'bc_field',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
