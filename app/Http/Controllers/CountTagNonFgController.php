<?php

namespace App\Http\Controllers;

use App\Enums\CountTagCondition;
use App\Http\Requests\StoreNonFgCountTagRequest;
use App\Http\Requests\UpdateNonFgCountTagRequest;
use App\Models\CountTagLocation;
use App\Models\CountTagSection;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Services\DocumentNumberService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use App\Support\CountTagPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
                try {
                    $start = CountTagPeriod::parse($dateStart)->startOfMonth()->toDateString();
                    $query->whereDate('count_tag_date', '>=', $start);
                } catch (\Throwable) {
                    // Ignore invalid filter.
                }
            })
            ->when($dateEnd !== '', function ($query) use ($dateEnd) {
                try {
                    $end = CountTagPeriod::toEndOfMonth($dateEnd);
                    $query->whereDate('count_tag_date', '<=', $end);
                } catch (\Throwable) {
                    // Ignore invalid filter.
                }
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
            'nextCtNumber' => app(DocumentNumberService::class)->previewNext('CT'),
            'conditions' => CountTagCondition::cases(),
            'today' => now()->toDateString(),
            'currentMonth' => CountTagPeriod::currentMonthString(),
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

        return response()->json($this->serializeTagDetail($nonFgCountTag));
    }

    public function scan(): View
    {
        return view('pages.count-tags.non-fg.scan', [
            'lookupUrl' => route('count-tags.non-fg.lookup'),
            'storeUrl' => route('count-tags.non-fg.store'),
            'locationsUrl' => route('count-tags.non-fg.locations'),
            'conditions' => CountTagCondition::cases(),
            'today' => now()->toDateString(),
            'currentMonth' => CountTagPeriod::currentMonthString(),
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

    public function store(StoreNonFgCountTagRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $item = Item::query()
            ->with(['unit:id,code,name'])
            ->whereKey((int) $validated['item_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $location = CountTagLocation::query()->findOrFail((int) $validated['location_id']);
        $section = null;
        if (! empty($validated['section_id'])) {
            $section = CountTagSection::query()
                ->whereKey((int) $validated['section_id'])
                ->where('location_id', $location->id)
                ->firstOrFail();
        }

        $condition = isset($validated['condition'])
            ? CountTagCondition::from($validated['condition'])
            : null;

        $tag = NonFgCountTag::query()->create([
            'count_tag_number' => null,
            'count_tag_date' => $validated['count_tag_date'],
            'item_id' => $item->id,
            'item_code' => $item->code,
            'item_category_id' => $item->category_id,
            'unit_of_measure_id' => $item->unit_of_measure_id,
            'uom_code' => $item->unit?->code ?? $item->unit?->name,
            'location_id' => $location->id,
            'location_name' => $location->name,
            'section_id' => $section?->id,
            'section_code' => $section?->code,
            'row' => $validated['row'] ?? null,
            'col' => $validated['col'] ?? null,
            'level' => $validated['level'] ?? null,
            'size' => isset($validated['size']) ? trim((string) $validated['size']) ?: null : null,
            'condition' => $condition,
            'qty' => $validated['qty'],
            'tran_date' => now()->toDateString(),
            'group_name' => null,
            'created_by' => $request->user()->id,
            'created_by_name' => $request->user()->name,
            'meta' => [
                'source' => 'scan',
            ],
        ]);

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

    public function update(
        UpdateNonFgCountTagRequest $request,
        NonFgCountTag $nonFgCountTag,
    ): JsonResponse {
        $validated = $request->validated();

        $location = CountTagLocation::query()->findOrFail((int) $validated['location_id']);
        $section = null;
        if (! empty($validated['section_id'])) {
            $section = CountTagSection::query()
                ->whereKey((int) $validated['section_id'])
                ->where('location_id', $location->id)
                ->firstOrFail();
        }

        $condition = isset($validated['condition'])
            ? CountTagCondition::from($validated['condition'])
            : null;

        $nonFgCountTag->update([
            'location_id' => $location->id,
            'location_name' => $location->name,
            'section_id' => $section?->id,
            'section_code' => $section?->code,
            'row' => $validated['row'] ?? null,
            'col' => $validated['col'] ?? null,
            'level' => $validated['level'] ?? null,
            'size' => isset($validated['size']) ? trim((string) $validated['size']) ?: null : null,
            'condition' => $condition,
            'qty' => $validated['qty'],
            'count_tag_date' => $validated['count_tag_date'],
            'meta' => array_merge($nonFgCountTag->meta ?? [], [
                'updated_via' => 'app',
                'updated_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => 'Count tag updated.',
            'data' => [
                'id' => $nonFgCountTag->id,
                'count_tag_number' => $nonFgCountTag->count_tag_number,
                'item_code' => $nonFgCountTag->item_code,
                'qty' => (float) $nonFgCountTag->qty,
            ],
        ]);
    }

    public function destroy(NonFgCountTag $nonFgCountTag): RedirectResponse|JsonResponse
    {
        $nonFgCountTag->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Count tag deleted.',
            ]);
        }

        return redirect()
            ->route('count-tags.non-fg.index')
            ->with('success', 'Count tag deleted.');
    }

    public function print(
        Request $request,
        NonFgCountTag $nonFgCountTag,
        DocumentNumberService $numberService,
    ): Response|RedirectResponse|SymfonyResponse {
        if ($request->isMethod('post') || $request->filled('count_tag_number')) {
            $this->assignCountTagNumber($request, $nonFgCountTag, $numberService);
            $nonFgCountTag->refresh();
        }

        if (trim((string) ($nonFgCountTag->count_tag_number ?? '')) === '') {
            return redirect()
                ->back()
                ->withErrors(['message' => 'Count tag number is required before printing.']);
        }

        $nonFgCountTag->load([
            'item:id,code,name',
            'itemCategory:id,name',
            'location:id,name',
            'section:id,code',
        ]);

        $pdf = Pdf::loadView('pdf.count-tag-non-fg-label', [
            'tag' => $nonFgCountTag,
        ])->setPaper([0, 0, 280, 180], 'portrait');

        $filename = sprintf(
            'count-tag-%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $nonFgCountTag->count_tag_number) ?: $nonFgCountTag->id
        );

        return $pdf->stream($filename);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'count_tag_date' => ['nullable', 'string', 'max:20'],
        ]);

        $code = trim($validated['code']);

        try {
            $period = isset($validated['count_tag_date']) && trim((string) $validated['count_tag_date']) !== ''
                ? CountTagPeriod::parse($validated['count_tag_date'])
                : now();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'count_tag_date' => 'Select a valid period month.',
            ]);
        }

        if (CountTagPeriod::isFutureMonth($period)) {
            throw ValidationException::withMessages([
                'count_tag_date' => 'Period month cannot be in the future.',
            ]);
        }

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

        $existing = $this->findTagForItemInPeriod($item->id, $period);
        $defaultsSource = $existing ?? $this->findLatestTagForItem($item->id);

        return response()->json([
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'unit_name' => $item->unit?->name,
            'category_name' => $item->category?->name,
            'existing' => $existing ? $this->serializeTagDefaults($existing) : null,
            'defaults' => $defaultsSource ? $this->serializeTagDefaults($defaultsSource) : null,
        ]);
    }

    private function assignCountTagNumber(
        Request $request,
        NonFgCountTag $tag,
        DocumentNumberService $numberService,
    ): void {
        $validated = $request->validate([
            'count_tag_number' => ['nullable', 'string', 'max:50'],
            'count_tag_number_suggested' => ['nullable', 'string', 'max:50'],
        ]);

        $current = trim((string) ($tag->count_tag_number ?? ''));
        if ($current !== '' && ! $request->filled('count_tag_number')) {
            return;
        }

        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolved = $numberService->resolve(
                'CT',
                $validated['count_tag_number'] ?? null,
                $validated['count_tag_number_suggested'] ?? null,
            );
            $number = $resolved['number'];
            $numberService->assertUnique('CT', $number, $tag->id);

            try {
                $tag->update(['count_tag_number' => $number]);

                return;
            } catch (QueryException $e) {
                if ($attempt >= $maxAttempts || ! $numberService->isDuplicateNumberException($e)) {
                    throw $e;
                }
            }
        }

        throw ValidationException::withMessages([
            'count_tag_number' => 'Unable to assign a unique count tag number.',
        ]);
    }

    private function findTagForItemInPeriod(int $itemId, Carbon $period): ?NonFgCountTag
    {
        return NonFgCountTag::query()
            ->where('item_id', $itemId)
            ->whereYear('count_tag_date', $period->year)
            ->whereMonth('count_tag_date', $period->month)
            ->latest('id')
            ->first();
    }

    private function findLatestTagForItem(int $itemId): ?NonFgCountTag
    {
        return NonFgCountTag::query()
            ->where('item_id', $itemId)
            ->latest('count_tag_date')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTagDefaults(NonFgCountTag $tag): array
    {
        return [
            'id' => $tag->id,
            'count_tag_number' => $tag->count_tag_number,
            'qty' => (float) $tag->qty,
            'location_id' => $tag->location_id,
            'section_id' => $tag->section_id,
            'row' => $tag->row,
            'col' => $tag->col,
            'level' => $tag->level,
            'condition' => $tag->condition?->value,
            'size' => $tag->size,
            'tran_date' => $tag->tran_date?->format('Y-m-d'),
            'count_tag_date' => $tag->count_tag_date?->format('Y-m-d'),
            'count_tag_month' => CountTagPeriod::toMonthString($tag->count_tag_date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTagDetail(NonFgCountTag $tag): array
    {
        return [
            'id' => $tag->id,
            'count_tag_number' => $tag->count_tag_number,
            'count_tag_date' => $tag->count_tag_date?->format('Y-m-d'),
            'count_tag_month' => CountTagPeriod::toMonthString($tag->count_tag_date),
            'tran_date' => $tag->tran_date?->format('Y-m-d'),
            'item_id' => $tag->item_id,
            'item_code' => $tag->item_code,
            'item_name' => $tag->item?->name,
            'category_name' => $tag->itemCategory?->name,
            'uom_code' => $tag->uom_code,
            'uom_name' => $tag->unitOfMeasure?->name,
            'location_id' => $tag->location_id,
            'location_name' => $tag->location?->name ?? $tag->location_name,
            'section_id' => $tag->section_id,
            'section_code' => $tag->section?->code ?? $tag->section_code,
            'row' => $tag->row,
            'col' => $tag->col,
            'level' => $tag->level,
            'size' => $tag->size,
            'condition' => $tag->condition?->value,
            'qty' => (float) $tag->qty,
            'created_by_name' => $tag->createdBy?->name ?? $tag->created_by_name,
            'update_url' => route('count-tags.non-fg.update', $tag),
            'print_url' => route('count-tags.non-fg.print', $tag),
            'destroy_url' => route('count-tags.non-fg.destroy', $tag),
        ];
    }
}
