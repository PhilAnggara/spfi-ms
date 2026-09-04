<?php

namespace App\Console\Commands;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountingInventoryValidateLegacyParityCommand extends Command
{
    protected $signature = 'accounting-inventory:validate-legacy-parity
                            {--year=2024 : Calendar year to compare}
                            {--month= : Optional month 1-12}
                            {--category= : Optional category name filter}';

    protected $description = 'Compare imported DocTran/monthly counts and qty sums against AISystem for a sample period';

    public function handle(): int
    {
        $year = (int) $this->option('year');
        $month = $this->option('month') !== null && $this->option('month') !== ''
            ? (int) $this->option('month')
            : null;
        $category = trim((string) ($this->option('category') ?: ''));

        try {
            DB::connection('legacy_sqlsrv_2')->selectOne('SELECT 1 as ok');
        } catch (Throwable $e) {
            $this->error('Cannot connect to legacy_sqlsrv_2: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Parity check year={$year}".($month ? " month={$month}" : '').($category !== '' ? " category={$category}" : ''));
        $this->newLine();

        $legacyDoc = DB::connection('legacy_sqlsrv_2')->table('DocTran')
            ->when($category !== '', fn ($q) => $q->where('Category', $category))
            ->whereRaw('YEAR(TranDate) = ?', [$year])
            ->when($month, fn ($q) => $q->whereRaw('MONTH(TranDate) = ?', [$month]));

        $localDoc = AccountingInventoryDocTran::query()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->whereRaw('YEAR(tran_date) = ?', [$year])
            ->when($month, fn ($q) => $q->whereRaw('MONTH(tran_date) = ?', [$month]));

        $legacyDocCount = (clone $legacyDoc)->count();
        $localDocCount = (clone $localDoc)->count();
        $legacyDocQty = (float) ((clone $legacyDoc)->sum('Qty') ?? 0);
        $localDocQty = (float) ((clone $localDoc)->sum('qty') ?? 0);

        $legacyMonthly = DB::connection('legacy_sqlsrv_2')->table('tbl_InventoryMonthly')
            ->when($category !== '', fn ($q) => $q->where('Category', $category))
            ->whereRaw('YEAR(TranDate) = ?', [$year])
            ->when($month, fn ($q) => $q->whereRaw('MONTH(TranDate) = ?', [$month]));

        $localMonthly = AccountingInventoryMonthly::query()
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->whereRaw('YEAR(tran_date) = ?', [$year])
            ->when($month, fn ($q) => $q->whereRaw('MONTH(tran_date) = ?', [$month]));

        $legacyMonthlyCount = (clone $legacyMonthly)->count();
        $localMonthlyCount = (clone $localMonthly)->count();
        $legacyMonthlyEnding = (float) ((clone $legacyMonthly)->sum('Ending') ?? 0);
        $localMonthlyEnding = (float) ((clone $localMonthly)->sum('ending') ?? 0);

        $rows = [
            ['DocTran count', $legacyDocCount, $localDocCount, $legacyDocCount - $localDocCount],
            ['DocTran qty sum', round($legacyDocQty, 5), round($localDocQty, 5), round($legacyDocQty - $localDocQty, 5)],
            ['Monthly count', $legacyMonthlyCount, $localMonthlyCount, $legacyMonthlyCount - $localMonthlyCount],
            ['Monthly ending sum', round($legacyMonthlyEnding, 5), round($localMonthlyEnding, 5), round($legacyMonthlyEnding - $localMonthlyEnding, 5)],
        ];

        $this->table(['Metric', 'Legacy AISystem', 'Local', 'Delta (legacy-local)'], $rows);

        $ok = $legacyDocCount === $localDocCount
            && abs($legacyDocQty - $localDocQty) < 0.0001
            && $legacyMonthlyCount === $localMonthlyCount
            && abs($legacyMonthlyEnding - $localMonthlyEnding) < 0.0001;

        if ($ok) {
            $this->info('Parity OK for selected filters.');

            return self::SUCCESS;
        }

        $this->warn('Parity differences detected. Re-run import or inspect unresolved masters.');

        return self::SUCCESS;
    }
}
