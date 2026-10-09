<?php

namespace App\Http\Controllers;

use App\Enums\CountTagCondition;
use App\Http\Requests\StoreNonFgCountTagRequest;
use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Services\DocumentNumberService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CountTagNonFgController extends Controller
{
    use PaginatesLegacySqlServer;

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('keyword'));
        $dateStart = trim((string) $request->query('date_start'));
        $dateEnd = trim((string) $request->query('date_end'));
        $locationId = trim((string) $request->query('location_id'));
        $categoryId = trim((string) $request->query('category_id'));

        $tagsQuery = NonFgCountTag::query()
            ->with([
                'item:id,code,name',
                'itemCategory:id,name',
                'location:id,name',
                'section:id,code',
            ])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($inner) use ($keyword) {
                    $inner->where('count_tag_number', 'like', "%{$keyword}%")
                        ->orWhere('item_code', 'like', "%{$keyword}%")
                        ->orWhere('location_name', 'like', "%{$keyword}%")
                        ->orWhere('created_by_name', 'like', "%{$keyword}%")
                        ->orWhereHas('itemCategory', function ($categoryQuery) use ($keyword) {
                            $categoryQuery->where('name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->when($dateStart !== '', function ($query) use ($dateStart) {
                $query->whereDate('count_tag_date', '>=', $dateStart);
            })
            ->when($dateEnd !== '', function ($query) use ($dateEnd) {
                $query->whereDate('count_tag_date', '<=', $dateEnd);
            })
            ->when($locationId !== '', function ($query) use ($locationId) {
                $query->where('location_id', (int) $locationId);
            })
            ->when($categoryId !== '', function ($query) use ($categoryId) {
                $query->where('item_category_id', (int) $categoryId);
            });

        $orderClause = 'non_fg_count_tags.count_tag_date DESC, non_fg_count_tags.id DESC';
        $tagsQuery->latest('count_tag_date')->latest('id');

        $tags = $this->paginateEloquentForCurrentConnection($tagsQuery, $orderClause, 20);

        $categoryIds = NonFgCountTag::query()
            ->whereNotNull('item_category_id')
            ->distinct()
            ->pluck('item_category_id');

        $categories = ItemCategory::query()
            ->when(
                $categoryIds->isNotEmpty(),
                fn ($query) => $query->whereIn('id', $categoryIds),
            )
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pages.count-tags.non-fg.index', [
            'tags' => $tags,
            'locations' => CountTagLocation::query()->orderBy('name')->get(['id', 'name']),
            'categories' => $categories,
            'filters' => [
                'keyword' => $keyword,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'location_id' => $locationId,
                'category_id' => $categoryId,
            ],
        ]);
    }

    public function show(NonFgCountTag $nonFgCountTag): JsonResponse
    {
        $nonFgCountTag->load([
            'item:id,code,name',
            'itemCategory:id,name',
            'unitOfMeasure:id,code,name',
            'location:id,name',
            'section:id,code',
            'createdBy:id,name',
        ]);

        return response()->json([
            'id' => $nonFgCountTag->id,
            'count_tag_number' => $nonFgCountTag->count_tag_number,
            'count_tag_date' => $nonFgCountTag->count_tag_date?->format('Y-m-d'),
            'tran_date' => $nonFgCountTag->tran_date?->format('Y-m-d'),
            'item_code' => $nonFgCountTag->item_code,
            'item_name' => $nonFgCountTag->item?->name,
            'category_name' => $nonFgCountTag->itemCategory?->name,
            'uom_code' => $nonFgCountTag->uom_code,
            'uom_name' => $nonFgCountTag->unitOfMeasure?->name,
            'location_name' => $nonFgCountTag->location?->name ?? $nonFgCountTag->location_name,
            'section_code' => $nonFgCountTag->section?->code ?? $nonFgCountTag->section_code,
            'row' => $nonFgCountTag->row,
            'col' => $nonFgCountTag->col,
            'level' => $nonFgCountTag->level,
            'size' => $nonFgCountTag->size,
            'condition' => $nonFgCountTag->condition?->value,
            'qty' => (float) $nonFgCountTag->qty,
            'created_by_name' => $nonFgCountTag->createdBy?->name ?? $nonFgCountTag->created_by_name,
        ]);
    }

    public function scan(): View
    {
        return view('pages.count-tags.non-fg.scan', [
            'lookupUrl' => route('count-tags.non-fg.lookup'),
            'storeUrl' => route('count-tags.non-fg.store'),
            'locationsUrl' => route('count-tags.non-fg.locations'),
            'conditions' => CountTagCondition::cases(),
            'today' => now()->toDateString(),
        ]);
    }

    public function locations(): JsonResponse
    {
        $locations = CountTagLocation::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $locations,
        ]);
    }

    public function sections(CountTagLocation $location): JsonResponse
    {
        $sections = CountTagSection::query()
            ->where('location_id', $location->id)
            ->orderBy('code')
            ->get(['id', 'code', 'max_row', 'max_column']);

        return response()->json([
            'data' => $sections,
        ]);
    }

    public function store(
        StoreNonFgCountTagRequest $request,
        DocumentNumberService $numberService,
    ): JsonResponse {
        $validated = $request->validated();

        $item = Item::query()
            ->with(['unit:id,code,name'])
            ->whereKey((int) $validated['item_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $location = CountTagLocation::query()->findOrFail((int) $validated['location_id']);
        $section = CountTagSection::query()
            ->whereKey((int) $validated['section_id'])
            ->where('location_id', $location->id)
            ->firstOrFail();

        $condition = isset($validated['condition'])
            ? CountTagCondition::from($validated['condition'])
            : null;

        $tag = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolved = $numberService->resolve('CT', null, null);
            $countTagNumber = $resolved['number'];
            $numberService->assertUnique('CT', $countTagNumber);

            try {
                $tag = DB::transaction(function () use ($request, $validated, $item, $location, $section, $condition, $countTagNumber) {
                    return NonFgCountTag::query()->create([
                        'legacy_id' => null,
                        'count_tag_number' => $countTagNumber,
                        'count_tag_date' => $validated['count_tag_date'],
                        'item_id' => $item->id,
                        'item_code' => $item->code,
                        'item_category_id' => $item->category_id,
                        'unit_of_measure_id' => $item->unit_of_measure_id,
                        'uom_code' => $item->unit?->code ?? $item->unit?->name,
                        'location_id' => $location->id,
                        'location_name' => $location->name,
                        'section_id' => $section->id,
                        'section_code' => $section->code,
                        'row' => $validated['row'] ?? null,
                        'col' => $validated['col'] ?? null,
                        'level' => $validated['level'] ?? null,
                        'size' => isset($validated['size']) ? trim((string) $validated['size']) ?: null : null,
                        'condition' => $condition,
                        'qty' => $validated['qty'],
                        'tran_date' => $validated['tran_date'],
                        'group_name' => null,
                        'created_by' => $request->user()->id,
                        'created_by_name' => $request->user()->name,
                        'meta' => [
                            'source' => 'scan',
                        ],
                    ]);
                });

                break;
            } catch (QueryException $e) {
                if ($attempt >= $maxAttempts || ! $numberService->isDuplicateNumberException($e)) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'message' => 'Count tag saved.',
            'data' => [
                'id' => $tag->id,
                'count_tag_number' => $tag->count_tag_number,
                'item_code' => $tag->item_code,
                'qty' => (float) $tag->qty,
            ],
        ], 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = trim($validated['code']);

        $item = Item::query()
            ->with([
                'unit:id,name',
                'category:id,name',
            ])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $item) {
            return response()->json([
                'message' => 'Product not found for code: '.$code,
            ], 404);
        }

        return response()->json([
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'unit_name' => $item->unit?->name,
            'category_name' => $item->category?->name,
        ]);
    }
}
