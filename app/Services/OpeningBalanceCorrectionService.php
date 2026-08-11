<?php

namespace App\Services;

use App\Models\Item;
use App\Models\OpeningBalanceCorrection;
use App\Models\OpeningBalanceCorrectionItem;
use App\Models\ReceivingReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceCorrectionService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array<int, array{item_id: int, new_beginning: float|int|string, wh_code?: string}>  $lines
     * @return list<array{
     *     item_id: int,
     *     product_code: string,
     *     wh_code: string,
     *     previous_beginning: float,
     *     new_beginning: float,
     *     delta_qty: float,
     *     replay_count: int
     * }>
     */
    public function preview(string $periodMonth, array $lines): array
    {
        $monthStart = Carbon::parse($periodMonth)->startOfMonth()->toDateString();
        $to = now()->toDateString();
        $previews = [];

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = Item::query()->find($itemId);
            if (! $item) {
                continue;
            }

            $whCode = (string) ($line['wh_code'] ?? StockService::DEFAULT_WH_CODE);
            $newBeginning = round((float) ($line['new_beginning'] ?? 0), 5);
            $previousBeginning = $this->stockService->impliedBeginning($itemId, $whCode, $monthStart);
            $jobs = $this->collectReplayJobs($itemId, $monthStart, $to);

            $previews[] = [
                'item_id' => $itemId,
                'product_code' => (string) $item->code,
                'wh_code' => $whCode,
                'previous_beginning' => $previousBeginning,
                'new_beginning' => $newBeginning,
                'delta_qty' => round($newBeginning - $previousBeginning, 5),
                'replay_count' => $jobs->count(),
            ];
        }

        return $previews;
    }

    /**
     * @param  array<int, array{item_id: int, new_beginning: float|int|string, wh_code?: string}>  $lines
     */
    public function apply(
        OpeningBalanceCorrection $correction,
        array $lines,
        ?int $userId = null
    ): void {
        $monthStart = Carbon::parse($correction->period_month)->startOfMonth()->toDateString();
        $openingDate = Carbon::parse($monthStart)->subDay()->toDateString();
        $to = now()->toDateString();
        $allowNegative = (bool) $correction->allow_negative_balance;

        foreach ($lines as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = Item::query()->find($itemId);
            if (! $item) {
                throw ValidationException::withMessages([
                    'items' => "Item #{$itemId} was not found.",
                ]);
            }

            $whCode = (string) ($line['wh_code'] ?? StockService::DEFAULT_WH_CODE);
            $newBeginning = round((float) ($line['new_beginning'] ?? 0), 5);

            DB::transaction(function () use (
                $correction,
                $item,
                $itemId,
                $whCode,
                $newBeginning,
                $monthStart,
                $openingDate,
                $to,
                $allowNegative,
                $userId
            ): void {
                $previousBeginning = $this->stockService->impliedBeginning($itemId, $whCode, $monthStart);
                $deltaQty = round($newBeginning - $previousBeginning, 5);
                $jobs = $this->collectReplayJobs($itemId, $monthStart, $to);

                $this->stockService->purgeItemMovementsFromDate($itemId, $whCode, $monthStart);

                $correctionItem = OpeningBalanceCorrectionItem::query()->create([
                    'opening_balance_correction_id' => $correction->id,
                    'item_id' => $itemId,
                    'product_code' => (string) $item->code,
                    'wh_code' => $whCode,
                    'previous_beginning' => $previousBeginning,
                    'new_beginning' => $newBeginning,
                    'delta_qty' => $deltaQty,
                    'replayed_movements' => $jobs->count(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                if (abs($deltaQty) >= 0.00001) {
                    $this->stockService->applyStockAdjustment(
                        stockAdjustmentId: $correction->id,
                        movementDate: $openingDate,
                        lines: [[
                            'item_id' => $itemId,
                            'product_code' => (string) $item->code,
                            'delta_qty' => $deltaQty,
                            'reference_line_id' => $correctionItem->id,
                            'wh_code' => $whCode,
                        ]],
                        userId: $userId,
                        allowNegativeBalance: true,
                        referenceType: StockService::REF_OPENING_BALANCE_CORRECTION,
                    );
                }

                $this->replayJobs($jobs, $allowNegative);
            });
        }
    }

    /**
     * Soft-delete reversal is not a full historical restore; OBC is intentionally one-way.
     * Deleting the document only reverses the opening adjustment delta and does not re-purge/replay.
     */
    public function reverseOpeningAdjustmentOnly(
        OpeningBalanceCorrection $correction,
        ?int $userId = null
    ): void {
        $monthStart = Carbon::parse($correction->period_month)->startOfMonth()->toDateString();
        $openingDate = Carbon::parse($monthStart)->subDay()->toDateString();

        $lines = $correction->items->map(fn (OpeningBalanceCorrectionItem $item): array => [
            'item_id' => (int) $item->item_id,
            'product_code' => (string) $item->product_code,
            'delta_qty' => (float) $item->delta_qty,
            'reference_line_id' => (int) $item->id,
            'wh_code' => (string) $item->wh_code,
        ])->all();

        $this->stockService->reverseStockAdjustment(
            stockAdjustmentId: $correction->id,
            movementDate: $openingDate,
            lines: $lines,
            userId: $userId,
            referenceType: StockService::REF_OPENING_BALANCE_CORRECTION,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectReplayJobs(int $itemId, string $from, string $to): Collection
    {
        return collect()
            ->merge($this->receivingJobs($itemId, $from, $to))
            ->merge($this->transferJobs($itemId, $from, $to))
            ->merge($this->deliveryJobs($itemId, $from, $to))
            ->sortBy([
                ['date', 'asc'],
                ['sort', 'asc'],
                ['reference_id', 'asc'],
                ['reference_line_id', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $jobs
     */
    private function replayJobs(Collection $jobs, bool $allowNegative): void
    {
        foreach ($jobs->groupBy(fn (array $job): string => $job['type'].':'.$job['reference_id']) as $group) {
            /** @var Collection<int, array<string, mixed>> $group */
            $first = $group->first();
            $this->postGroup($group, $first, $allowNegative);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @param  array<string, mixed>  $first
     */
    private function postGroup(Collection $group, array $first, bool $allowNegative): void
    {
        if ($first['type'] === 'RR') {
            $receivingReport = ReceivingReport::query()->findOrFail($first['reference_id']);
            $lines = [];
            foreach ($group as $job) {
                $lines[(int) $job['reference_line_id']] = [
                    'purchase_order_item_id' => (int) $job['reference_line_id'],
                    'item_id' => (int) $job['item_id'],
                    'product_code' => (string) $job['product_code'],
                    'qty_good' => (float) $job['quantity'],
                    'unit_price' => (float) $job['unit_price'],
                ];
            }

            $this->stockService->applyReceivingReportAdjustment(
                receivingReport: $receivingReport,
                currentLines: $lines,
                previousLines: [],
                userId: null,
                allowNegativeBalance: $allowNegative,
            );

            return;
        }

        $lines = $group->map(fn (array $job): array => [
            'item_id' => (int) $job['item_id'],
            'product_code' => (string) $job['product_code'],
            'quantity' => (float) $job['quantity'],
            'reference_line_id' => (int) $job['reference_line_id'],
        ])->values()->all();

        if ($first['type'] === 'TS') {
            $this->stockService->applyTransferSlipIssue(
                transferSlipId: (int) $first['reference_id'],
                movementDate: (string) $first['date'],
                lines: $lines,
                userId: null,
                allowNegativeBalance: $allowNegative,
            );

            return;
        }

        $this->stockService->applyDeliveryIssue(
            deliveryId: (int) $first['reference_id'],
            movementDate: (string) $first['date'],
            lines: $lines,
            userId: null,
            allowNegativeBalance: $allowNegative,
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function receivingJobs(int $itemId, string $from, string $to): Collection
    {
        $rows = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->where('poi.item_id', $itemId)
            ->whereDate('rr.received_date', '>=', $from)
            ->whereDate('rr.received_date', '<=', $to)
            ->where('rri.qty_good', '>', 0)
            ->select([
                'rr.id as reference_id',
                'rr.received_date as date',
                'rri.purchase_order_item_id as reference_line_id',
                'poi.item_id',
                'i.code as product_code',
                'rri.qty_good as quantity',
                'poi.unit_price',
            ])
            ->orderBy('rr.received_date')
            ->orderBy('rr.id')
            ->get();

        return $rows->map(fn ($row): array => [
            'type' => 'RR',
            'sort' => 1,
            'date' => Carbon::parse($row->date)->toDateString(),
            'reference_id' => (int) $row->reference_id,
            'reference_line_id' => (int) $row->reference_line_id,
            'item_id' => (int) $row->item_id,
            'product_code' => (string) $row->product_code,
            'quantity' => (float) $row->quantity,
            'unit_price' => (float) $row->unit_price,
        ])->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function transferJobs(int $itemId, string $from, string $to): Collection
    {
        $rows = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->where('tsi.item_id', $itemId)
            ->whereDate('ts.ts_date', '>=', $from)
            ->whereDate('ts.ts_date', '<=', $to)
            ->where('tsi.quantity', '>', 0)
            ->select([
                'ts.id as reference_id',
                'ts.ts_date as date',
                'tsi.id as reference_line_id',
                'tsi.item_id',
                'tsi.product_code',
                'tsi.quantity',
            ])
            ->orderBy('ts.ts_date')
            ->orderBy('ts.id')
            ->get();

        return $rows->map(fn ($row): array => [
            'type' => 'TS',
            'sort' => 2,
            'date' => Carbon::parse($row->date)->toDateString(),
            'reference_id' => (int) $row->reference_id,
            'reference_line_id' => (int) $row->reference_line_id,
            'item_id' => (int) $row->item_id,
            'product_code' => (string) ($row->product_code ?: ''),
            'quantity' => (float) $row->quantity,
            'unit_price' => 0,
        ])->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function deliveryJobs(int $itemId, string $from, string $to): Collection
    {
        $rows = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->where('di.item_id', $itemId)
            ->whereDate('d.dr_date', '>=', $from)
            ->whereDate('d.dr_date', '<=', $to)
            ->where('di.quantity', '>', 0)
            ->select([
                'd.id as reference_id',
                'd.dr_date as date',
                'di.id as reference_line_id',
                'di.item_id',
                'di.product_code',
                'di.quantity',
            ])
            ->orderBy('d.dr_date')
            ->orderBy('d.id')
            ->get();

        return $rows->map(fn ($row): array => [
            'type' => 'DR',
            'sort' => 3,
            'date' => Carbon::parse($row->date)->toDateString(),
            'reference_id' => (int) $row->reference_id,
            'reference_line_id' => (int) $row->reference_line_id,
            'item_id' => (int) $row->item_id,
            'product_code' => (string) ($row->product_code ?: ''),
            'quantity' => (float) $row->quantity,
            'unit_price' => 0,
        ])->values();
    }
}
