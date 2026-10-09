<?php

namespace App\Http\Controllers;

use App\Models\CountTagLocation;
use App\Models\Item;
use App\Models\NonFgCountTag;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                        ->orWhere('created_by_name', 'like', "%{$keyword}%");
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
            });

        $orderClause = 'non_fg_count_tags.count_tag_date DESC, non_fg_count_tags.id DESC';
        $tagsQuery->latest('count_tag_date')->latest('id');

        $tags = $this->paginateEloquentForCurrentConnection($tagsQuery, $orderClause, 20);

        return view('pages.count-tags.non-fg.index', [
            'tags' => $tags,
            'locations' => CountTagLocation::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'keyword' => $keyword,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'location_id' => $locationId,
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
            'legacy_id' => $nonFgCountTag->legacy_id,
            'count_tag_number' => $nonFgCountTag->count_tag_number,
            'count_tag_date' => $nonFgCountTag->count_tag_date?->format('Y-m-d'),
            'tran_date' => $nonFgCountTag->tran_date?->format('Y-m-d'),
            'item_code' => $nonFgCountTag->item_code,
            'item_name' => $nonFgCountTag->item?->name,
            'item_matched' => $nonFgCountTag->item_id !== null,
            'category_name' => $nonFgCountTag->itemCategory?->name,
            'uom_code' => $nonFgCountTag->uom_code,
            'uom_name' => $nonFgCountTag->unitOfMeasure?->name,
            'location_name' => $nonFgCountTag->location?->name ?? $nonFgCountTag->location_name,
            'location_matched' => $nonFgCountTag->location_id !== null,
            'section_code' => $nonFgCountTag->section?->code ?? $nonFgCountTag->section_code,
            'row' => $nonFgCountTag->row,
            'col' => $nonFgCountTag->col,
            'level' => $nonFgCountTag->level,
            'size' => $nonFgCountTag->size,
            'condition' => $nonFgCountTag->condition?->value,
            'qty' => (float) $nonFgCountTag->qty,
            'group_name' => $nonFgCountTag->group_name,
            'created_by_name' => $nonFgCountTag->createdBy?->name ?? $nonFgCountTag->created_by_name,
            'meta' => $nonFgCountTag->meta,
        ]);
    }

    public function scan(): View
    {
        return view('pages.count-tags.non-fg.scan', [
            'lookupUrl' => route('count-tags.non-fg.lookup'),
        ]);
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
