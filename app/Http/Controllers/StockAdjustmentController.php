<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use App\Support\Concerns\SearchesStockCorrectionItems;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    use PaginatesLegacySqlServer;
    use SearchesStockCorrectionItems;

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('keyword'));
        $dateStart = trim((string) $request->query('date_start'));
        $dateEnd = trim((string) $request->query('date_end'));

        $adjustmentsQuery = StockAdjustment::query()
            ->with(['createdBy:id,name', 'items'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($inner) use ($keyword) {
                    $inner->where('sa_number', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%");
                });
            })
            ->when($dateStart !== '', function ($query) use ($dateStart) {
                $query->whereDate('sa_date', '>=', $dateStart);
            })
            ->when($dateEnd !== '', function ($query) use ($dateEnd) {
                $query->whereDate('sa_date', '<=', $dateEnd);
            });

        $orderClause = 'stock_adjustments.sa_date DESC, stock_adjustments.id DESC';
        $adjustmentsQuery->latest('sa_date')->latest('id');

        $adjustments = $this->paginateEloquentForCurrentConnection($adjustmentsQuery, $orderClause, 20);

        return view('pages.stock-adjustments.index', [
            'adjustments' => $adjustments,
            'filters' => [
                'keyword' => $keyword,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ],
        ]);
    }

    public function create(DocumentNumberService $numberService): View
    {
        return view('pages.stock-adjustments.create', [
            'nextSaNumber' => $numberService->previewNext('SA'),
            'itemSearchUrl' => route('stock-adjustments.items.search'),
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        return $this->searchStockCorrectionItems($request);
    }

    public function store(
        StoreStockAdjustmentRequest $request,
        DocumentNumberService $numberService,
        StockService $stockService
    ): RedirectResponse {
        $lines = $this->buildAdjustmentLines($request->validated('items'), $stockService);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => 'No balance changes to post. New balance must differ from current stock.',
            ]);
        }

        $adjustment = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolved = $numberService->resolve(
                'SA',
                $request->input('sa_number'),
                $request->input('sa_number_suggested')
            );
            $saNumber = $resolved['number'];
            $numberService->assertUnique('SA', $saNumber);

            try {
                $adjustment = DB::transaction(function () use ($request, $saNumber, $lines, $stockService) {
                    $adjustment = StockAdjustment::query()->create([
                        'sa_number' => $saNumber,
                        'sa_date' => $request->validated('sa_date'),
                        'reason' => $request->validated('reason'),
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]);

                    $postLines = [];

                    foreach ($lines as $line) {
                        $item = StockAdjustmentItem::query()->create([
                            'stock_adjustment_id' => $adjustment->id,
                            'item_id' => $line['item_id'],
                            'product_code' => $line['product_code'],
                            'wh_code' => $line['wh_code'],
                            'previous_balance' => $line['previous_balance'],
                            'new_balance' => $line['new_balance'],
                            'delta_qty' => $line['delta_qty'],
                            'created_by' => $request->user()->id,
                            'updated_by' => $request->user()->id,
                        ]);

                        $postLines[] = [
                            'item_id' => $line['item_id'],
                            'product_code' => $line['product_code'],
                            'delta_qty' => $line['delta_qty'],
                            'reference_line_id' => $item->id,
                            'wh_code' => $line['wh_code'],
                        ];
                    }

                    $stockService->applyStockAdjustment(
                        stockAdjustmentId: $adjustment->id,
                        movementDate: (string) $request->validated('sa_date'),
                        lines: $postLines,
                        userId: $request->user()->id,
                    );

                    return $adjustment;
                });
                break;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (QueryException $exception) {
                $canRetry = $resolved['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                if ($numberService->isDuplicateNumberException($exception)) {
                    $numberService->assertUnique('SA', $saNumber);

                    throw ValidationException::withMessages([
                        'sa_number' => "The SA Number {$saNumber} has already been used.",
                    ]);
                }

                throw $exception;
            }
        }

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', "Stock adjustment {$adjustment->sa_number} posted.");
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load(['items.item:id,name,code', 'createdBy:id,name']);

        return view('pages.stock-adjustments.show', [
            'adjustment' => $stockAdjustment,
        ]);
    }

    public function destroy(
        StockAdjustment $stockAdjustment,
        StockService $stockService
    ): RedirectResponse {
        $stockAdjustment->load('items');
        $releasedNumber = $stockAdjustment->sa_number;

        DB::transaction(function () use ($stockAdjustment, $stockService) {
            $lines = $stockAdjustment->items->map(fn (StockAdjustmentItem $item): array => [
                'item_id' => (int) $item->item_id,
                'product_code' => (string) $item->product_code,
                'delta_qty' => (float) $item->delta_qty,
                'reference_line_id' => (int) $item->id,
                'wh_code' => (string) $item->wh_code,
            ])->all();

            $stockService->reverseStockAdjustment(
                stockAdjustmentId: $stockAdjustment->id,
                movementDate: $stockAdjustment->sa_date->toDateString(),
                lines: $lines,
                userId: auth()->id(),
            );

            $stockAdjustment->items()->delete();
            $stockAdjustment->updated_by = auth()->id();
            $stockAdjustment->sa_number = 'DELETED-'.$stockAdjustment->id;
            $stockAdjustment->save();
            $stockAdjustment->delete();
        });

        return redirect()
            ->route('stock-adjustments.index')
            ->with('success', "Stock adjustment {$releasedNumber} deleted and stock reversed.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{item_id: int, product_code: string, wh_code: string, previous_balance: float, new_balance: float, delta_qty: float}>
     */
    private function buildAdjustmentLines(array $items, StockService $stockService): array
    {
        $lines = [];

        foreach ($items as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $item = Item::query()->find($itemId);
            if (! $item) {
                continue;
            }

            $whCode = trim((string) ($row['wh_code'] ?? StockService::DEFAULT_WH_CODE)) ?: StockService::DEFAULT_WH_CODE;
            $previous = $stockService->currentBalance($itemId, $whCode);
            $newBalance = round((float) ($row['new_balance'] ?? 0), 5);
            $delta = round($newBalance - $previous, 5);

            if (abs($delta) < 0.00001) {
                continue;
            }

            $lines[] = [
                'item_id' => $itemId,
                'product_code' => (string) $item->code,
                'wh_code' => $whCode,
                'previous_balance' => $previous,
                'new_balance' => $newBalance,
                'delta_qty' => $delta,
            ];
        }

        return $lines;
    }
}
