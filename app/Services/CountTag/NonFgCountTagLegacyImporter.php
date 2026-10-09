<?php

namespace App\Services\CountTag;

use App\Enums\CountTagCondition;
use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Models\UnitOfMeasure;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class NonFgCountTagLegacyImporter
{
    private string $connection;

    /** @var Collection<string, int> */
    private Collection $locationsByName;

    /** @var Collection<int, int> */
    private Collection $sectionsByLocationAndCode;

    /** @var Collection<string, int> */
    private Collection $itemsByCode;

    /** @var Collection<string, int> */
    private Collection $localCategoriesByName;

    /** @var Collection<string, int> */
    private Collection $uomsByCode;

    /** @var Collection<int, string> */
    private Collection $legacyCategories;

    public function __construct(
        private readonly NonFgCountTagCategoryMapper $categoryMapper,
        private readonly NonFgCountTagLocationResolver $locationResolver,
    ) {
        $this->connection = (string) config('accounting_inventory.count_tag.connection', 'legacy_sqlsrv_5');
        $this->locationsByName = collect();
        $this->sectionsByLocationAndCode = collect();
        $this->itemsByCode = collect();
        $this->localCategoriesByName = collect();
        $this->uomsByCode = collect();
        $this->legacyCategories = collect();
    }

    /**
     * @param  callable(int, int): void|null  $onChunkProgress
     * @return array<string, mixed>
     */
    public function import(int $chunkSize = 500, ?callable $onChunkProgress = null): array
    {
        $this->warmLocalLookups();

        $locationStats = $this->importLocations();
        $sectionStats = $this->importSections();
        $this->refreshLocationLookups();

        $tagStats = [
            'created' => 0,
            'updated' => 0,
            'unmatched_items' => 0,
            'unmatched_categories' => 0,
            'unmatched_locations' => 0,
        ];

        $lastId = 0;

        do {
            $rows = DB::connection($this->connection)
                ->table('tblNonFGCountTag')
                ->where('Id', '>', $lastId)
                ->orderBy('Id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->Id;
                $result = $this->upsertCountTag($row);

                if ($result['created']) {
                    $tagStats['created']++;
                } else {
                    $tagStats['updated']++;
                }

                if (! $result['item_matched']) {
                    $tagStats['unmatched_items']++;
                }

                if (! $result['category_matched']) {
                    $tagStats['unmatched_categories']++;
                }

                if (! $result['location_matched']) {
                    $tagStats['unmatched_locations']++;
                }
            }

            if ($onChunkProgress !== null) {
                $onChunkProgress($lastId, $rows->count());
            }
        } while ($rows->count() === $chunkSize);

        return [
            'locations' => $locationStats,
            'sections' => $sectionStats,
            'tags' => $tagStats,
        ];
    }

    /**
     * @return array{created: int, updated: int}
     */
    private function importLocations(): array
    {
        $created = 0;
        $updated = 0;

        $rows = DB::connection($this->connection)
            ->table('tblLocation')
            ->orderBy('Id')
            ->get(['Id', 'Location']);

        foreach ($rows as $row) {
            $name = strtoupper(trim((string) $row->Location));
            $legacyId = (int) $row->Id;

            $location = CountTagLocation::query()->updateOrCreate(
                ['legacy_id' => $legacyId],
                [
                    'name' => $name,
                    'meta' => ['legacy' => (array) $row],
                ],
            );

            if ($location->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array{created: int, updated: int}
     */
    private function importSections(): array
    {
        $created = 0;
        $updated = 0;

        $rows = DB::connection($this->connection)
            ->table('tblSection')
            ->orderBy('Id')
            ->get(['Id', 'LocationId', 'Section', 'Row', 'Column']);

        foreach ($rows as $row) {
            $location = CountTagLocation::query()
                ->where('legacy_id', (int) $row->LocationId)
                ->first();

            if ($location === null) {
                continue;
            }

            $section = CountTagSection::query()->updateOrCreate(
                ['legacy_id' => (int) $row->Id],
                [
                    'location_id' => $location->id,
                    'code' => strtoupper(trim((string) $row->Section)),
                    'max_row' => (int) ($row->Row ?? 0),
                    'max_column' => (int) ($row->Column ?? 0),
                    'meta' => ['legacy' => (array) $row],
                ],
            );

            if ($section->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array{created: bool, item_matched: bool, category_matched: bool, location_matched: bool}
     */
    private function upsertCountTag(object $row): array
    {
        $legacyId = (int) $row->Id;
        $itemCode = $this->normalizeItemCode($row->ItemCode ?? '');
        $itemId = $this->itemsByCode->get($itemCode);
        $legacyCategoryId = (int) ($row->Category ?? -1);
        $categoryId = $this->categoryMapper->resolveCategoryId(
            $legacyCategoryId,
            $this->legacyCategories,
            $this->localCategoriesByName,
        );

        $locationResult = $this->locationResolver->resolve($row->Location ?? null, $this->locationsByName);
        $locationId = $locationResult['location_id'];
        $sectionCode = strtoupper(trim((string) ($row->SectionLocation ?? '')));
        $sectionId = null;

        if ($locationId !== null && $sectionCode !== '') {
            $sectionId = $this->sectionsByLocationAndCode->get("{$locationId}:{$sectionCode}");
        }

        $uomCode = trim((string) ($row->UOM ?? ''));
        $uomId = $uomCode !== '' ? $this->uomsByCode->get(strtoupper($uomCode)) : null;

        $condition = CountTagCondition::tryFromLegacy($row->Condition ?? null);
        $groupName = trim((string) ($row->Grp ?? ''));

        $meta = [
            'legacy' => (array) $row,
            'mapping' => [
                'category_legacy_id' => $legacyCategoryId,
                'category_resolved_id' => $categoryId,
                'location' => $locationResult['notes'],
                'item_normalized_code' => $itemCode,
            ],
        ];

        if ($categoryId === null && $legacyCategoryId >= 0) {
            $meta['mapping']['category_unmapped'] = true;
        }

        $attributes = [
            'count_tag_number' => trim((string) ($row->CountTag ?? '')) ?: null,
            'count_tag_date' => $this->parseDate($row->CountTagDate ?? null),
            'item_id' => $itemId,
            'item_code' => $itemCode !== '' ? $itemCode : trim((string) ($row->ItemCode ?? '')),
            'item_category_id' => $categoryId,
            'unit_of_measure_id' => $uomId,
            'uom_code' => $uomCode !== '' ? $uomCode : null,
            'location_id' => $locationId,
            'location_name' => $locationResult['location_name'],
            'section_id' => $sectionId,
            'section_code' => $sectionCode !== '' ? $sectionCode : null,
            'row' => $this->nullableInt($row->Row ?? null),
            'col' => $this->nullableInt($row->Col ?? null),
            'level' => $this->nullableInt($row->Levl ?? null),
            'size' => trim((string) ($row->Size ?? '')) ?: null,
            'condition' => $condition,
            'qty' => (float) ($row->QTY ?? 0),
            'tran_date' => $this->parseDate($row->Trandate ?? null),
            'group_name' => $groupName !== '' ? $groupName : null,
            'created_by' => null,
            'created_by_name' => $groupName !== '' ? $groupName : null,
            'meta' => $meta,
        ];

        $existing = NonFgCountTag::query()->where('legacy_id', $legacyId)->exists();

        NonFgCountTag::query()->updateOrCreate(
            ['legacy_id' => $legacyId],
            $attributes,
        );

        return [
            'created' => ! $existing,
            'item_matched' => $itemId !== null,
            'category_matched' => $categoryId !== null || $legacyCategoryId < 0,
            'location_matched' => $locationId !== null || trim((string) ($row->Location ?? '')) === '',
        ];
    }

    private function warmLocalLookups(): void
    {
        $this->itemsByCode = Item::query()
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Item $item): array => [$this->normalizeItemCode($item->code) => $item->id]);

        $this->localCategoriesByName = ItemCategory::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ItemCategory $category): array => [strtoupper(trim($category->name)) => $category->id]);

        $this->uomsByCode = UnitOfMeasure::query()
            ->get(['id', 'code', 'name'])
            ->flatMap(function (UnitOfMeasure $uom): array {
                $entries = [];

                if (trim((string) $uom->code) !== '') {
                    $entries[strtoupper(trim((string) $uom->code))] = $uom->id;
                }

                if (trim((string) $uom->name) !== '') {
                    $entries[strtoupper(trim((string) $uom->name))] = $uom->id;
                }

                return $entries;
            });

        try {
            $this->legacyCategories = DB::connection($this->connection)
                ->table('tblCategory')
                ->get(['Id', 'CategoryName'])
                ->mapWithKeys(fn (object $row): array => [(int) $row->Id => strtoupper(trim((string) $row->CategoryName))]);
        } catch (Throwable) {
            $this->legacyCategories = collect();
        }
    }

    private function refreshLocationLookups(): void
    {
        $this->locationsByName = CountTagLocation::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (CountTagLocation $location): array => [strtoupper(trim($location->name)) => $location->id]);

        $this->sectionsByLocationAndCode = CountTagSection::query()
            ->get(['id', 'location_id', 'code'])
            ->mapWithKeys(fn (CountTagSection $section): array => [
                "{$section->location_id}:".strtoupper(trim($section->code)) => $section->id,
            ]);
    }

    private function normalizeItemCode(mixed $code): string
    {
        $value = strtoupper(trim((string) $code));

        return preg_replace('/\s+/', '', $value) ?? $value;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }
}
