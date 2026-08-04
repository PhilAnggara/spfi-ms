<?php

namespace App\Console\Commands;

use App\Models\ReceivingReport;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class StockBackfillCurrentMonthCommand extends Command
{
    protected $signature = 'stock:backfill-current-month
                            {--date= : Anchor date when --from is omitted (default: today)}
                            {--from= : Start date Y-m-d (overrides month window)}
                            {--to= : End date Y-m-d (default: --date or today)}
                            {--rebuild : Purge then re-post all RR/TS/DR stock in the window}
                            {--dry-run : List jobs without writing}
                            {--force : Allow negative stock balances when posting issues}';

    protected $description = 'Backfill missing (or rebuild) RR/TS/DR stock ledger rows for a date window';

    public function handle(StockService $stockService): int
    {
        [$from, $to] = $this->resolveWindow();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $rebuild = (bool) $this->option('rebuild');

        $this->info(sprintf(
            'Stock window: %s .. %s%s%s%s',
            $from,
            $to,
            $rebuild ? ' (rebuild)' : ' (missing-only)',
            $dryRun ? ' (dry-run)' : '',
            $force ? ' (force)' : '',
        ));

        $allJobs = $this->collectJobs($from, $to, includeAliases: true);
        $stockableJobs = $this->collectJobs($from, $to, includeAliases: false);
        $jobs = $rebuild
            ? $stockableJobs
            : $stockableJobs->reject(fn (array $job) => $this->jobAlreadyPosted($stockService, $job))->values();

        $aliasSkipped = $allJobs->count() - $stockableJobs->count();
        if ($aliasSkipped > 0) {
            $this->warn("Skipping {$aliasSkipped} reconcile-alias line(s) for stock (kept for audit only).");
        }

        if ($jobs->isEmpty() && ! ($rebuild && $allJobs->isNotEmpty())) {
            $this->info($rebuild
                ? 'No RR/TS/DR documents found in this window.'
                : 'No missing RR/TS/DR stock postings found in this window.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'Date', 'Doc ID', 'Line ID', 'Item', 'Qty', 'Posted'],
            $jobs->map(fn (array $job): array => [
                $job['type'],
                $job['date'],
                $job['reference_id'],
                $job['reference_line_id'],
                $job['product_code'],
                number_format((float) $job['quantity'], 2, '.', ''),
                $this->jobAlreadyPosted($stockService, $job) ? 'yes' : 'no',
            ])->all()
        );

        if ($dryRun) {
            $this->warn(sprintf(
                'Dry-run complete. %d line(s) would be %s.',
                $jobs->count(),
                $rebuild ? 'purged (incl. aliases) then re-posted' : 'posted'
            ));

            return self::SUCCESS;
        }

        $purged = 0;
        $posted = 0;
        $failed = 0;

        if ($rebuild) {
            // Purge stock for every doc in the window, including aliases, so duplicate alias posts are removed.
            [$purged, $purgeFailed] = $this->purgePostedGroups($stockService, $allJobs);
            $failed += $purgeFailed;
            $this->info("Purged ledger rows: {$purged}. Purge failures: {$purgeFailed}.");
        }

        foreach ($jobs->groupBy(fn (array $job): string => $job['type'].':'.$job['reference_id']) as $group) {
            /** @var Collection<int, array<string, mixed>> $group */
            $first = $group->first();

            try {
                DB::transaction(function () use ($stockService, $group, $first, $force, &$posted): void {
                    $this->postGroup($stockService, $group, $first, $force);
                    $posted += $group->count();
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
     * @return array{0: string, 1: string}
     */
    private function resolveWindow(): array
    {
        $asOf = Carbon::parse($this->option('date') ?: now()->toDateString())->startOfDay();
        $fromOption = trim((string) $this->option('from'));
        $toOption = trim((string) $this->option('to'));

        if ($fromOption !== '') {
            $from = Carbon::parse($fromOption)->toDateString();
            $to = $toOption !== ''
                ? Carbon::parse($toOption)->toDateString()
                : $asOf->toDateString();
        } else {
            $from = $asOf->copy()->startOfMonth()->toDateString();
            $to = $toOption !== ''
                ? Carbon::parse($toOption)->toDateString()
                : $asOf->toDateString();
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectJobs(string $from, string $to, bool $includeAliases): Collection
    {
        $aliasRrIds = $this->aliasDocumentIds('rr', 'receiving_reports', 'rr_number');
        $aliasTsIds = $this->aliasDocumentIds('ts', 'transfer_slips', 'ts_number');
        $aliasDrIds = $this->aliasDocumentIds('dr', 'deliveries', 'dr_number');

        return collect()
            ->merge($this->receivingJobs($from, $to, $includeAliases ? collect() : $aliasRrIds))
            ->merge($this->transferJobs($from, $to, $includeAliases ? collect() : $aliasTsIds))
            ->merge($this->deliveryJobs($from, $to, $includeAliases ? collect() : $aliasDrIds))
            ->sortBy([
                ['date', 'asc'],
                ['sort', 'asc'],
                ['reference_id', 'asc'],
                ['reference_line_id', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function aliasDocumentIds(string $mapType, string $table, string $numberColumn): Collection
    {
        $ids = collect();

        if (Schema::hasTable('reconciliation_number_maps')) {
            $aliasNumbers = DB::table('reconciliation_number_maps')
                ->where('document_type', $mapType)
                ->where('resolution', 'import_as_alias')
                ->pluck('spfi_number')
                ->filter()
                ->values();

            if ($aliasNumbers->isNotEmpty()) {
                $ids = $ids->merge(
                    DB::table($table)->whereIn($numberColumn, $aliasNumbers->all())->pluck('id')
                );
            }
        }

        if (Schema::hasColumn($table, 'meta')) {
            $metaAliased = DB::table($table)
                ->whereNotNull('meta')
                ->where(function ($query): void {
                    $query->where('meta', 'like', '%"aliased_from":"%')
                        ->where('meta', 'not like', '%"aliased_from":null%')
                        ->where('meta', 'not like', '%"aliased_from":""%');
                })
                ->pluck('id');
            $ids = $ids->merge($metaAliased);
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $jobs
     * @return array{0: int, 1: int}
     */
    private function purgePostedGroups(StockService $stockService, Collection $jobs): array
    {
        $purged = 0;
        $failed = 0;

        $groups = $jobs->groupBy(fn (array $job): string => $job['type'].':'.$job['reference_id']);

        foreach ($groups as $group) {
            /** @var Collection<int, array<string, mixed>> $group */
            $first = $group->first();
            $referenceType = match ($first['type']) {
                'RR' => StockService::REF_RECEIVING_REPORT,
                'TS' => StockService::REF_TRANSFER_SLIP,
                default => StockService::REF_DELIVERY,
            };

            try {
                DB::transaction(function () use ($stockService, $referenceType, $first, &$purged): void {
                    $purged += $stockService->purgeDocumentMovements(
                        $referenceType,
                        (int) $first['reference_id'],
                    );
                });
            } catch (Throwable $exception) {
                $failed += $group->count();
                $this->error(sprintf(
                    'Purge failed %s #%s: %s',
                    $first['type'],
                    $first['reference_id'],
                    $exception->getMessage()
                ));
            }
        }

        return [$purged, $failed];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @param  array<string, mixed>  $first
     */
    private function postGroup(StockService $stockService, Collection $group, array $first, bool $force): void
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

            $stockService->applyReceivingReportAdjustment(
                receivingReport: $receivingReport,
                currentLines: $lines,
                previousLines: [],
                userId: null,
                allowNegativeBalance: $force,
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
            $stockService->applyTransferSlipIssue(
                transferSlipId: (int) $first['reference_id'],
                movementDate: (string) $first['date'],
                lines: $lines,
                userId: null,
                allowNegativeBalance: $force,
            );

            return;
        }

        $stockService->applyDeliveryIssue(
            deliveryId: (int) $first['reference_id'],
            movementDate: (string) $first['date'],
            lines: $lines,
            userId: null,
            allowNegativeBalance: $force,
        );
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function jobAlreadyPosted(StockService $stockService, array $job): bool
    {
        $referenceType = match ($job['type']) {
            'RR' => StockService::REF_RECEIVING_REPORT,
            'TS' => StockService::REF_TRANSFER_SLIP,
            default => StockService::REF_DELIVERY,
        };

        return $stockService->hasPostedReference(
            $referenceType,
            (int) $job['reference_id'],
            (int) $job['reference_line_id']
        );
    }

    /**
     * @param  Collection<int, int>  $excludeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function receivingJobs(string $from, string $to, Collection $excludeIds): Collection
    {
        $query = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->whereDate('rr.received_date', '>=', $from)
            ->whereDate('rr.received_date', '<=', $to)
            ->where('rri.qty_good', '>', 0);

        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('rr.id', $excludeIds->all());
        }

        $rows = $query->select([
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
     * @param  Collection<int, int>  $excludeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function transferJobs(string $from, string $to, Collection $excludeIds): Collection
    {
        $query = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereDate('ts.ts_date', '>=', $from)
            ->whereDate('ts.ts_date', '<=', $to)
            ->where('tsi.quantity', '>', 0);

        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('ts.id', $excludeIds->all());
        }

        $rows = $query->select([
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
            'product_code' => (string) $row->product_code,
            'quantity' => (float) $row->quantity,
            'unit_price' => 0,
        ])->values();
    }

    /**
     * @param  Collection<int, int>  $excludeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function deliveryJobs(string $from, string $to, Collection $excludeIds): Collection
    {
        $query = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->whereDate('d.dr_date', '>=', $from)
            ->whereDate('d.dr_date', '<=', $to)
            ->where('di.quantity', '>', 0);

        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('d.id', $excludeIds->all());
        }

        $rows = $query->select([
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
            'product_code' => (string) $row->product_code,
            'quantity' => (float) $row->quantity,
            'unit_price' => 0,
        ])->values();
    }
}
