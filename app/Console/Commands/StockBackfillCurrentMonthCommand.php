<?php

namespace App\Console\Commands;

use App\Models\ReceivingReport;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StockBackfillCurrentMonthCommand extends Command
{
    protected $signature = 'stock:backfill-current-month
                            {--date= : Use this date to determine the month window (default: today)}
                            {--dry-run : List missing rows without posting}
                            {--force : Allow negative stock balances when posting issues}';

    protected $description = 'Backfill missing RR/TS/DR stock ledger rows for the current calendar month only';

    public function handle(StockService $stockService): int
    {
        $asOf = Carbon::parse($this->option('date') ?: now()->toDateString())->startOfDay();
        $from = $asOf->copy()->startOfMonth()->toDateString();
        $to = $asOf->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("Backfill window: {$from} .. {$to}".($dryRun ? ' (dry-run)' : '').($force ? ' (force)' : ''));

        $jobs = collect()
            ->merge($this->missingReceivingJobs($from, $to, $stockService))
            ->merge($this->missingTransferJobs($from, $to, $stockService))
            ->merge($this->missingDeliveryJobs($from, $to, $stockService))
            ->sortBy([
                ['date', 'asc'],
                ['sort', 'asc'],
                ['reference_id', 'asc'],
                ['reference_line_id', 'asc'],
            ])
            ->values();

        if ($jobs->isEmpty()) {
            $this->info('No missing RR/TS/DR stock postings found for this month.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'Date', 'Doc ID', 'Line ID', 'Item', 'Qty'],
            $jobs->map(fn (array $job): array => [
                $job['type'],
                $job['date'],
                $job['reference_id'],
                $job['reference_line_id'],
                $job['product_code'],
                number_format((float) $job['quantity'], 2, '.', ''),
            ])->all()
        );

        if ($dryRun) {
            $this->warn("Dry-run complete. {$jobs->count()} missing line(s) would be posted.");

            return self::SUCCESS;
        }

        $posted = 0;
        $failed = 0;

        foreach ($jobs->groupBy(fn (array $job): string => $job['type'].':'.$job['reference_id']) as $group) {
            /** @var Collection<int, array<string, mixed>> $group */
            $first = $group->first();

            try {
                DB::transaction(function () use ($stockService, $group, $first, $force, &$posted): void {
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

                        $stockService->applyReceivingReportAdjustment(
                            receivingReport: $receivingReport,
                            currentLines: $lines,
                            previousLines: [],
                            userId: null,
                            allowNegativeBalance: $force,
                        );
                        $posted += count($lines);

                        return;
                    }

                    $lines = $group->map(fn (array $job): array => [
                        'item_id' => (int) $job['item_id'],
                        'product_code' => (string) $job['product_code'],
                        'quantity' => (float) $job['quantity'],
                        'reference_line_id' => (int) $job['reference_line_id'],
                    ])->values()->all();

                    if ($first['type'] === 'TS') {
                        $stockService->applyTransferSlipIssue(
                            transferSlipId: (int) $first['reference_id'],
                            movementDate: (string) $first['date'],
                            lines: $lines,
                            userId: null,
                            allowNegativeBalance: $force,
                        );
                    } else {
                        $stockService->applyDeliveryIssue(
                            deliveryId: (int) $first['reference_id'],
                            movementDate: (string) $first['date'],
                            lines: $lines,
                            userId: null,
                            allowNegativeBalance: $force,
                        );
                    }

                    $posted += count($lines);
                });
            } catch (ValidationException $exception) {
                $failed += $group->count();
                $this->error(sprintf(
                    'Failed %s #%s: %s',
                    $first['type'],
                    $first['reference_id'],
                    collect($exception->errors())->flatten()->implode('; ')
                ));
            } catch (Throwable $exception) {
                $failed += $group->count();
                $this->error(sprintf(
                    'Failed %s #%s: %s',
                    $first['type'],
                    $first['reference_id'],
                    $exception->getMessage()
                ));
            }
        }

        $this->info("Posted: {$posted}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function missingReceivingJobs(string $from, string $to, StockService $stockService): Collection
    {
        $rows = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
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

        return $rows
            ->reject(fn ($row) => $stockService->hasPostedReference(
                StockService::REF_RECEIVING_REPORT,
                (int) $row->reference_id,
                (int) $row->reference_line_id
            ))
            ->map(fn ($row): array => [
                'type' => 'RR',
                'sort' => 1,
                'date' => (string) $row->date,
                'reference_id' => (int) $row->reference_id,
                'reference_line_id' => (int) $row->reference_line_id,
                'item_id' => (int) $row->item_id,
                'product_code' => (string) $row->product_code,
                'quantity' => (float) $row->quantity,
                'unit_price' => (float) $row->unit_price,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function missingTransferJobs(string $from, string $to, StockService $stockService): Collection
    {
        $rows = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
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

        return $rows
            ->reject(fn ($row) => $stockService->hasPostedReference(
                StockService::REF_TRANSFER_SLIP,
                (int) $row->reference_id,
                (int) $row->reference_line_id
            ))
            ->map(fn ($row): array => [
                'type' => 'TS',
                'sort' => 2,
                'date' => (string) $row->date,
                'reference_id' => (int) $row->reference_id,
                'reference_line_id' => (int) $row->reference_line_id,
                'item_id' => (int) $row->item_id,
                'product_code' => (string) $row->product_code,
                'quantity' => (float) $row->quantity,
                'unit_price' => 0,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function missingDeliveryJobs(string $from, string $to, StockService $stockService): Collection
    {
        $rows = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
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

        return $rows
            ->reject(fn ($row) => $stockService->hasPostedReference(
                StockService::REF_DELIVERY,
                (int) $row->reference_id,
                (int) $row->reference_line_id
            ))
            ->map(fn ($row): array => [
                'type' => 'DR',
                'sort' => 3,
                'date' => (string) $row->date,
                'reference_id' => (int) $row->reference_id,
                'reference_line_id' => (int) $row->reference_line_id,
                'item_id' => (int) $row->item_id,
                'product_code' => (string) $row->product_code,
                'quantity' => (float) $row->quantity,
                'unit_price' => 0,
            ])
            ->values();
    }
}
