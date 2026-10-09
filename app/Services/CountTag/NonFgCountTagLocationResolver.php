<?php

namespace App\Services\CountTag;

use Illuminate\Support\Collection;

class NonFgCountTagLocationResolver
{
    /**
     * Common legacy typos / aliases normalized to canonical location names.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'BONTIO' => 'BONITO',
        'BON ITO' => 'BONITO',
        'BONITO`' => 'BONITO',
    ];

    /**
     * @param  Collection<string, int>  $locationsByName  uppercase name => id
     * @return array{location_id: ?int, location_name: ?string, notes: array<string, mixed>}
     */
    public function resolve(?string $rawLocation, Collection $locationsByName): array
    {
        $raw = trim((string) $rawLocation);

        if ($raw === '' || strcasecmp($raw, 'Select Location') === 0) {
            return [
                'location_id' => null,
                'location_name' => null,
                'notes' => ['raw_location' => $rawLocation],
            ];
        }

        $normalized = $this->normalizeLocationName($raw);
        $canonical = self::ALIASES[$normalized] ?? $normalized;

        $locationId = $locationsByName->get($canonical);

        return [
            'location_id' => $locationId,
            'location_name' => $locationId !== null ? $canonical : $raw,
            'notes' => [
                'raw_location' => $rawLocation,
                'normalized_location' => $normalized,
                'canonical_location' => $canonical,
            ],
        ];
    }

    public function normalizeLocationName(string $value): string
    {
        $cleaned = str_replace('`', '', $value);
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned)) ?? trim($cleaned);

        return strtoupper($cleaned);
    }
}
