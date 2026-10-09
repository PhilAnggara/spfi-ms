<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountTagSection extends Model
{
    /** @use HasFactory<\Database\Factories\CountTagSectionFactory> */
    use HasFactory;

    protected $table = 'count_tag_sections';

    protected $fillable = [
        'location_id',
        'code',
        'max_row',
        'max_column',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'location_id' => 'integer',
            'max_row' => 'integer',
            'max_column' => 'integer',
            'legacy_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(CountTagLocation::class, 'location_id');
    }

    public function countTags(): HasMany
    {
        return $this->hasMany(NonFgCountTag::class, 'section_id');
    }
}
