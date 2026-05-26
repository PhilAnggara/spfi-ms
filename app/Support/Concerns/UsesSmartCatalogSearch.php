<?php

namespace App\Support\Concerns;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait UsesSmartCatalogSearch
{
    private function smartCatalogPaginator(Builder $baseQuery, string $search, int $perPage = 36): LengthAwarePaginator
    {
        $normalizedSearch = $this->normalizeCatalogSearchValue($search);
        $searchTerms = $this->catalogSearchTerms($normalizedSearch);
        $canUseSmartSearch = $searchTerms->isNotEmpty() && strlen(str_replace(' ', '', $normalizedSearch)) >= 3;

        if ($canUseSmartSearch) {
            return $this->searchCatalogItems($baseQuery, $normalizedSearch, $searchTerms, $perPage);
        }

        $itemsQuery = (clone $baseQuery)
            ->with(['unit', 'category']);

        if ($searchTerms->isNotEmpty()) {
            $this->applyCatalogBasicSearch($itemsQuery, $searchTerms);
        }

        $itemsQuery
            ->orderBy('name')
            ->orderBy('id');

        return $this->paginateEloquentForCurrentConnection($itemsQuery, 'name ASC, id ASC', $perPage);
    }

    private function applyCatalogBasicSearch(Builder $query, Collection $searchTerms): void
    {
        foreach ($searchTerms as $term) {
            $query->where(function (Builder $subQuery) use ($term) {
                $subQuery
                    ->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%' . $term . '%'])
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($term) {
                        $categoryQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    })
                    ->orWhereHas('unit', function (Builder $unitQuery) use ($term) {
                        $unitQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    });
            });
        }
    }

    private function searchCatalogItems(Builder $baseQuery, string $normalizedSearch, Collection $searchTerms, int $perPage): LengthAwarePaginator
    {
        $candidateLimit = min(5000, max(2500, $perPage * 100));
        $totalAvailable = (clone $baseQuery)->count();

        if ($totalAvailable <= 2500) {
            $candidates = (clone $baseQuery)
                ->with(['unit', 'category'])
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        } else {
            $candidateQuery = (clone $baseQuery)
                ->with(['unit', 'category']);

            $this->applyCatalogFuzzyCandidateSearch($candidateQuery, $normalizedSearch, $searchTerms);

            $candidates = $candidateQuery
                ->orderBy('name')
                ->orderBy('id')
                ->limit($candidateLimit)
                ->get();

            if ($candidates->isEmpty()) {
                $candidates = (clone $baseQuery)
                    ->with(['unit', 'category'])
                    ->orderBy('name')
                    ->orderBy('id')
                    ->limit($candidateLimit)
                    ->get();
            }
        }

        $rankedItems = $candidates
            ->map(function (Item $item) use ($normalizedSearch, $searchTerms): array {
                return [
                    'item' => $item,
                    'score' => $this->scoreCatalogItem($item, $normalizedSearch, $searchTerms),
                    'normalized_name' => $this->normalizeCatalogSearchValue($item->name),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['score'] > 0)
            ->sort(function (array $left, array $right): int {
                $scoreCompare = $right['score'] <=> $left['score'];
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                $nameCompare = strcmp($left['normalized_name'], $right['normalized_name']);
                if ($nameCompare !== 0) {
                    return $nameCompare;
                }

                return $left['item']->id <=> $right['item']->id;
            })
            ->values()
            ->pluck('item');

        return $this->paginateCatalogCollection($rankedItems, $perPage);
    }

    private function applyCatalogFuzzyCandidateSearch(Builder $query, string $normalizedSearch, Collection $searchTerms): void
    {
        $compactQuery = str_replace(' ', '', $normalizedSearch);
        $fragments = $this->catalogSearchFragments($compactQuery);

        $query->where(function (Builder $nested) use ($searchTerms, $fragments, $compactQuery) {
            if ($compactQuery !== '') {
                $nested
                    ->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $compactQuery . '%'])
                    ->orWhereRaw("REPLACE(LOWER(code), ' ', '') LIKE ?", ['%' . $compactQuery . '%'])
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($compactQuery) {
                        $categoryQuery->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $compactQuery . '%']);
                    })
                    ->orWhereHas('unit', function (Builder $unitQuery) use ($compactQuery) {
                        $unitQuery->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $compactQuery . '%']);
                    });
            }

            foreach ($searchTerms as $term) {
                $nested
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%' . $term . '%'])
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($term) {
                        $categoryQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    })
                    ->orWhereHas('unit', function (Builder $unitQuery) use ($term) {
                        $unitQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    });
            }

            foreach ($fragments as $fragment) {
                $nested
                    ->orWhereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $fragment . '%'])
                    ->orWhereRaw("REPLACE(LOWER(code), ' ', '') LIKE ?", ['%' . $fragment . '%'])
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($fragment) {
                        $categoryQuery->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $fragment . '%']);
                    })
                    ->orWhereHas('unit', function (Builder $unitQuery) use ($fragment) {
                        $unitQuery->whereRaw("REPLACE(LOWER(name), ' ', '') LIKE ?", ['%' . $fragment . '%']);
                    });
            }
        });
    }

    private function scoreCatalogItem(Item $item, string $normalizedSearch, Collection $searchTerms): float
    {
        $compactQuery = str_replace(' ', '', $normalizedSearch);
        $fields = $this->catalogSearchFields($item);
        $score = 0.0;

        foreach ($fields as $field) {
            $value = $field['compact'];
            $weight = $field['weight'];

            if ($value === '' || $compactQuery === '') {
                continue;
            }

            if ($value === $compactQuery) {
                $score += 1400 * $weight;
                continue;
            }

            if (str_starts_with($value, $compactQuery)) {
                $score += 1000 * $weight;
            }

            if (str_contains($value, $compactQuery)) {
                $lengthPenalty = max(0, strlen($value) - strlen($compactQuery)) * 6;
                $score += max(450, 820 - $lengthPenalty) * $weight;
            }

            $wholeSimilarity = max(
                $this->catalogSimilarityScore($compactQuery, [$value]),
                $this->catalogEditDistanceScore($compactQuery, [$value])
            );

            if ($wholeSimilarity >= 0.9) {
                $score += 360 * $weight;
            } elseif ($wholeSimilarity >= 0.8) {
                $score += 220 * $weight;
            } elseif ($wholeSimilarity >= 0.7) {
                $score += 120 * $weight;
            }

            if ($field['acronym'] !== '') {
                if ($field['acronym'] === $compactQuery) {
                    $score += 320 * $weight;
                } elseif (str_starts_with($field['acronym'], $compactQuery)) {
                    $score += 180 * $weight;
                }
            }
        }

        foreach ($searchTerms as $term) {
            $termCompact = str_replace(' ', '', $term);
            if ($termCompact === '') {
                continue;
            }

            $termMatched = false;

            foreach ($fields as $field) {
                $tokens = $field['tokens'];
                $weight = $field['weight'];

                foreach ($tokens as $token) {
                    if ($token === $termCompact) {
                        $score += 300 * $weight;
                        $termMatched = true;
                        continue;
                    }

                    if (str_starts_with($token, $termCompact)) {
                        $score += 220 * $weight;
                        $termMatched = true;
                        continue;
                    }

                    if (str_contains($token, $termCompact)) {
                        $score += 160 * $weight;
                        $termMatched = true;
                    }
                }

                $termSimilarity = max(
                    $this->catalogSimilarityScore($termCompact, $tokens),
                    $this->catalogEditDistanceScore($termCompact, $tokens)
                );

                if ($termSimilarity >= 0.92) {
                    $score += 240 * $weight;
                    $termMatched = true;
                } elseif ($termSimilarity >= 0.84) {
                    $score += 160 * $weight;
                    $termMatched = true;
                } elseif ($termSimilarity >= 0.75) {
                    $score += 85 * $weight;
                    $termMatched = true;
                }

                if ($this->catalogHasPhoneticMatch($termCompact, $field['phonetic_keys'])) {
                    $score += 170 * $weight;
                    $termMatched = true;
                }

                if ($field['acronym'] !== '' && $field['acronym'] === $termCompact) {
                    $score += 190 * $weight;
                    $termMatched = true;
                }
            }

            if (!$termMatched) {
                return 0.0;
            }
        }

        $queryFragments = $this->catalogSearchFragments($compactQuery);
        if ($queryFragments !== []) {
            $allTokens = [];
            foreach ($fields as $field) {
                $allTokens = array_merge($allTokens, $field['tokens']);
            }

            $matchedFragments = 0;

            foreach ($queryFragments as $fragment) {
                foreach ($allTokens as $token) {
                    if (str_contains($token, $fragment)) {
                        $matchedFragments += 1;
                        break;
                    }
                }
            }

            $score += $matchedFragments * 18;
        }

        return round($score, 3);
    }

    private function catalogSearchFields(Item $item): array
    {
        $fields = [
            [
                'value' => $this->normalizeCatalogSearchValue($item->name),
                'weight' => 1.0,
            ],
            [
                'value' => $this->normalizeCatalogSearchValue($item->code),
                'weight' => 1.15,
            ],
            [
                'value' => $this->normalizeCatalogSearchValue($item->category?->name),
                'weight' => 0.6,
            ],
            [
                'value' => $this->normalizeCatalogSearchValue($item->unit?->name),
                'weight' => 0.35,
            ],
        ];

        return collect($fields)
            ->filter(fn (array $field): bool => $field['value'] !== '')
            ->map(function (array $field): array {
                $tokens = $this->catalogTokenArray($field['value']);

                return [
                    'value' => $field['value'],
                    'compact' => str_replace(' ', '', $field['value']),
                    'tokens' => $tokens,
                    'weight' => $field['weight'],
                    'acronym' => $this->catalogAcronym($tokens),
                    'phonetic_keys' => $this->catalogPhoneticKeys($tokens),
                ];
            })
            ->values()
            ->all();
    }

    private function catalogSimilarityScore(string $needle, array $candidates): float
    {
        $best = 0.0;
        $needleLength = strlen($needle);

        foreach ($candidates as $candidate) {
            $normalizedCandidate = str_replace(' ', '', (string) $candidate);
            if ($normalizedCandidate === '') {
                continue;
            }

            $lengthDifference = abs(strlen($normalizedCandidate) - $needleLength);
            if ($lengthDifference > max(3, (int) floor($needleLength * 0.65))
                && !str_contains($normalizedCandidate, $needle)
                && !str_contains($needle, $normalizedCandidate)) {
                continue;
            }

            similar_text($needle, $normalizedCandidate, $percent);
            $best = max($best, $percent / 100);
        }

        return $best;
    }

    private function catalogEditDistanceScore(string $needle, array $candidates): float
    {
        $best = 0.0;
        $needleLength = strlen($needle);

        foreach ($candidates as $candidate) {
            $normalizedCandidate = str_replace(' ', '', (string) $candidate);
            if ($normalizedCandidate === '') {
                continue;
            }

            $maxLength = max($needleLength, strlen($normalizedCandidate));
            if ($maxLength === 0) {
                continue;
            }

            $distance = levenshtein($needle, $normalizedCandidate);
            if ($distance > max(3, (int) floor($maxLength * 0.45))
                && !str_contains($normalizedCandidate, $needle)
                && !str_contains($needle, $normalizedCandidate)) {
                continue;
            }

            $best = max($best, 1 - ($distance / $maxLength));
        }

        return $best;
    }

    private function catalogHasPhoneticMatch(string $needle, array $candidateKeys): bool
    {
        $needleKeys = $this->catalogPhoneticKeys([$needle]);
        if ($needleKeys === [] || $candidateKeys === []) {
            return false;
        }

        return count(array_intersect($needleKeys, $candidateKeys)) > 0;
    }

    private function normalizeCatalogSearchValue(?string $value): string
    {
        return (string) Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }

    private function catalogSearchTerms(string $normalizedSearch): Collection
    {
        return collect(explode(' ', $normalizedSearch))
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->values();
    }

    private function catalogTokenArray(string $value): array
    {
        return $this->catalogSearchTerms($value)
            ->map(fn ($token) => str_replace(' ', '', (string) $token))
            ->filter()
            ->values()
            ->all();
    }

    private function catalogAcronym(array $tokens): string
    {
        if ($tokens === []) {
            return '';
        }

        return implode('', array_map(fn (string $token): string => $token[0], $tokens));
    }

    private function catalogPhoneticKeys(array $tokens): array
    {
        $keys = [];

        foreach ($tokens as $token) {
            if (strlen($token) < 2) {
                continue;
            }

            $metaphone = metaphone($token);
            if ($metaphone !== '') {
                $keys[] = 'm:' . $metaphone;
            }

            $soundex = soundex($token);
            if ($soundex !== '') {
                $keys[] = 's:' . $soundex;
            }
        }

        return array_values(array_unique($keys));
    }

    private function catalogSearchFragments(string $value): array
    {
        $compactValue = str_replace(' ', '', $value);
        $length = strlen($compactValue);
        if ($length < 3) {
            return [];
        }

        $windowSize = $length <= 5 ? 2 : 3;
        $fragments = [];

        for ($index = 0; $index <= $length - $windowSize; $index++) {
            $fragments[] = substr($compactValue, $index, $windowSize);
        }

        return array_values(array_unique(array_filter($fragments)));
    }

    private function paginateCatalogCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = $currentPage > 0 ? $currentPage : 1;
        $total = $items->count();
        $results = $items->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
