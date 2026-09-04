<?php

namespace App\Console\Commands;

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountingInventoryImportLegacyCommand extends Command
{
    protected $signature = 'accounting-inventory:import-legacy
                            {--from-year=2024 : Import rows with TranDate on/after this year}
                            {--dry-run : Count and preview without writing}
                            {--only= : doctran,monthly, or both (default both)}
                            {--chunk= : Chunk size (default 100 on sqlsrv, 500 otherwise)}';

    protected $description = 'Import AISystem DocTran + tbl_InventoryMonthly into legacy-shaped accounting inventory tables';

    /** @var array<string, int> */
    private array $itemIdByCode = [];

    /** @var array<string, int> */
    private array $categoryIdByName = [];

    public function handle(): int
    {
        $fromYear = max(1990, (int) $this->option('from-year'));
        $fromDate = sprintf('%04d-01-01', $fromYear);
        $dryRun = (bool) $this->option('dry-run');
        $chunk = $this->resolveChunkSize();
        $only = strtolower(trim((string) ($this->option('only') ?: 'both')));

        if (! in_array($only, ['both', 'doctran', 'monthly'], true)) {
            $this->error('Invalid --only. Use both, doctran, or monthly.');

            return self::FAILURE;
        }

        $this->info('Accounting inventory legacy import');
        $this->line('  app db: '.config('database.default').' / '.config('database.connections.'.config('database.default').'.database'));
        $this->line("  from: {$fromDate}");
        $this->line('  mode: '.($dryRun ? 'DRY-RUN' : 'APPLY'));
        $this->line("  only: {$only}");
        $this->line("  chunk: {$chunk}");
        $this->newLine();

        try {
            DB::connection('legacy_sqlsrv_2')->selectOne('SELECT 1 as ok');
        } catch (Throwable $e) {
            $this->error('Cannot connect to legacy_sqlsrv_2 (AISystem): '.$e->getMessage());

            return self::FAILURE;
        }

        $this->warmMasterMaps();

        $stats = [
            'doctran_seen' => 0,
            'doctran_imported' => 0,
            'doctran_skipped' => 0,
            'doctran_unresolved_item' => 0,
            'doctran_unresolved_category' => 0,
            'monthly_seen' => 0,
            'monthly_imported' => 0,
            'monthly_skipped' => 0,
        ];

        if (in_array($only, ['both', 'doctran'], true)) {
            $this->importDocTran($fromDate, $chunk, $dryRun, $stats);
        }

        if (in_array($only, ['both', 'monthly'], true)) {
            $this->importMonthly($fromDate, $chunk, $dryRun, $stats);
        }

        $this->newLine();
        $this->info('Summary');
        foreach ($stats as $key => $value) {
            $this->line(sprintf('  %-28s %d', $key, $value));
        }

        return self::SUCCESS;
    }

    private function warmMasterMaps(): void
    {
        $this->itemIdByCode = Item::query()
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code): array => [strtoupper(trim((string) $code)) => (int) $id])
            ->all();

        $this->categoryIdByName = ItemCategory::query()
            ->whereNotNull('name')
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name): array => [strtoupper(trim((string) $name)) => (int) $id])
            ->all();
    }

    /**
     * @return array{item_id: ?int, category_id: ?int}
     */
    private function resolveMasterIds(string $itemCode, string $categoryName): array
    {
        $itemKey = strtoupper(trim($itemCode));
        $categoryKey = strtoupper(trim($categoryName));

        return [
            'item_id' => $this->itemIdByCode[$itemKey] ?? null,
            'category_id' => $this->categoryIdByName[$categoryKey] ?? null,
        ];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function importDocTran(string $fromDate, int $chunk, bool $dryRun, array &$stats): void
    {
        $this->info('Importing DocTran...');

        $lastId = 0;
        do {
            $rows = DB::connection('legacy_sqlsrv_2')
                ->table('DocTran')
                ->where('TranId', '>', $lastId)
                ->where('TranDate', '>=', $fromDate)
                ->orderBy('TranId')
                ->limit($chunk)
                ->get([
                    'TranId', 'DocCode', 'DocNo', 'DocDate', 'PoNo', 'ICode', 'Qty', 'UCost', 'Uom',
                    'AveCost', 'TQty', 'TranDate', 'InputTime', 'ModifyDate', 'Category', 'Amount',
                ]);

            if ($rows->isEmpty()) {
                break;
            }

            $legacyIds = $rows->pluck('TranId')->map(fn ($id): int => (int) $id)->all();
            $existing = $dryRun
                ? []
                : AccountingInventoryDocTran::query()
                    ->whereIn('legacy_tran_id', $legacyIds)
                    ->pluck('legacy_tran_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            $existingLookup = array_fill_keys($existing, true);

            $payload = [];
            foreach ($rows as $row) {
                $stats['doctran_seen']++;
                $lastId = (int) $row->TranId;
                if (isset($existingLookup[$lastId])) {
                    $stats['doctran_skipped']++;

                    continue;
                }

                $masters = $this->resolveMasterIds((string) $row->ICode, (string) $row->Category);
                if ($masters['item_id'] === null) {
                    $stats['doctran_unresolved_item']++;
                }
                if ($masters['category_id'] === null) {
                    $stats['doctran_unresolved_category']++;
                }

                $payload[] = [
                    'legacy_tran_id' => $lastId,
                    'doc_code' => trim((string) $row->DocCode),
                    'doc_no' => trim((string) $row->DocNo),
                    'doc_date' => substr((string) $row->DocDate, 0, 10),
                    'po_no' => $this->nullableString($row->PoNo ?? null),
                    'item_code' => trim((string) $row->ICode),
                    'qty' => (float) $row->Qty,
                    'u_cost' => (float) $row->UCost,
                    'uom' => $this->nullableString($row->Uom ?? null),
                    'ave_cost' => $row->AveCost !== null ? (float) $row->AveCost : null,
                    't_qty' => $row->TQty !== null ? (float) $row->TQty : null,
                    'tran_date' => substr((string) $row->TranDate, 0, 10),
                    'input_time' => $this->normalizeTime($row->InputTime ?? null),
                    'modify_date' => $row->ModifyDate ? substr((string) $row->ModifyDate, 0, 19) : null,
                    'category' => trim((string) $row->Category),
                    'amount' => (float) $row->Amount,
                    'item_id' => $masters['item_id'],
                    'category_id' => $masters['category_id'],
                    'created_at' => $this->nowTimestamp(),
                    'updated_at' => $this->nowTimestamp(),
                ];
                $stats['doctran_imported']++;
            }

            if (! $dryRun && $payload !== []) {
                AccountingInventoryDocTran::query()->insert($payload);
            }

            $this->output->write('.');
        } while ($rows->count() === $chunk);

        $this->newLine();
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function importMonthly(string $fromDate, int $chunk, bool $dryRun, array &$stats): void
    {
        $this->info('Importing tbl_InventoryMonthly...');

        $lastId = 0;
        do {
            $rows = DB::connection('legacy_sqlsrv_2')
                ->table('tbl_InventoryMonthly')
                ->where('ID', '>', $lastId)
                ->where('TranDate', '>=', $fromDate)
                ->orderBy('ID')
                ->limit($chunk)
                ->get([
                    'ID', 'ItemCode', 'DocCode', 'DocNo', 'Qty', 'UCost', 'Begining', 'Ending',
                    'TranDate', 'Category', 'BeginingUCost',
                ]);

            if ($rows->isEmpty()) {
                break;
            }

            $legacyIds = $rows->pluck('ID')->map(fn ($id): int => (int) $id)->all();
            $existing = $dryRun
                ? []
                : AccountingInventoryMonthly::query()
                    ->whereIn('legacy_monthly_id', $legacyIds)
                    ->pluck('legacy_monthly_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
            $existingLookup = array_fill_keys($existing, true);

            $payload = [];
            foreach ($rows as $row) {
                $stats['monthly_seen']++;
                $lastId = (int) $row->ID;
                if (isset($existingLookup[$lastId])) {
                    $stats['monthly_skipped']++;

                    continue;
                }

                $masters = $this->resolveMasterIds((string) $row->ItemCode, (string) $row->Category);

                $payload[] = [
                    'legacy_monthly_id' => $lastId,
                    'item_code' => trim((string) $row->ItemCode),
                    'doc_code' => trim((string) $row->DocCode),
                    'doc_no' => trim((string) $row->DocNo),
                    'qty' => (float) $row->Qty,
                    'u_cost' => (float) $row->UCost,
                    'begining' => (float) $row->Begining,
                    'ending' => (float) $row->Ending,
                    'tran_date' => substr((string) $row->TranDate, 0, 10),
                    'category' => trim((string) $row->Category),
                    'begining_u_cost' => $row->BeginingUCost !== null ? (float) $row->BeginingUCost : null,
                    'item_id' => $masters['item_id'],
                    'category_id' => $masters['category_id'],
                    'created_at' => $this->nowTimestamp(),
                    'updated_at' => $this->nowTimestamp(),
                ];
                $stats['monthly_imported']++;
            }

            if (! $dryRun && $payload !== []) {
                AccountingInventoryMonthly::query()->insert($payload);
            }

            $this->output->write('.');
        } while ($rows->count() === $chunk);

        $this->newLine();
    }

    private function resolveChunkSize(): int
    {
        $requested = $this->option('chunk');
        $driver = (string) config('database.default');
        $default = $driver === 'sqlsrv' ? 100 : 500;
        $chunk = max(20, (int) ($requested !== null && $requested !== '' ? $requested : $default));

        // SQL Server PDO limit is 2100 parameters per query.
        if ($driver === 'sqlsrv') {
            $chunk = min($chunk, 100);
        }

        return $chunk;
    }

    private function nowTimestamp(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;
        if (preg_match('/^(\d{2}:\d{2}:\d{2})/', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
