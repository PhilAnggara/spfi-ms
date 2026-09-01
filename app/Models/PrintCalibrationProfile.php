<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintCalibrationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\PrintCalibrationProfileFactory> */
    use HasFactory;

    public const DOCUMENT_TYPE_RR = 'RR';

    public const DOCUMENT_TYPE_TS = 'TS';

    /** @var list<string> */
    public const DOCUMENT_TYPES = [
        self::DOCUMENT_TYPE_RR,
        self::DOCUMENT_TYPE_TS,
    ];

    protected $fillable = [
        'document_type',
        'name',
        'measured_anchor_x_mm',
        'measured_anchor_y_mm',
        'is_default',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'measured_anchor_x_mm' => 'float',
            'measured_anchor_y_mm' => 'float',
            'is_default' => 'boolean',
        ];
    }

    public static function defaultFor(string $documentType): ?self
    {
        return self::query()
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public static function forDocumentType(string $documentType): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('document_type', $documentType)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
