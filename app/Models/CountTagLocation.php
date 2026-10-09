<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountTagLocation extends Model
{
    /** @use HasFactory<\Database\Factories\CountTagLocationFactory> */
    use HasFactory;

    protected $table = 'count_tag_locations';

    protected $fillable = [
        'name',
        'legacy_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'legacy_id' => 'integer',
            'meta' => 'array',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CountTagSection::class, 'location_id');
    }

    public function countTags(): HasMany
    {
        return $this->hasMany(NonFgCountTag::class, 'location_id');
    }
}
