<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\StockBalance;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StockRechainCommand extends Command
{
    protected $signature = 'stock:rechain
                            {item_code : Item product code}
                            {--from= : Start date Y-m-d (required)}
                            {--wh=MAIN : Warehouse code}
                            {--dry-run : Show current last end without writing}';

    protected $description = 'Recast stock_balances begin/end for an item from a date forward (does not change on-hand)';

    public function handle(StockService $stockService): int
    {
        $itemCode = trim((string) $this->argument('item_code'));
        $from = trim((string) $this->option('from'));
        $whCode = trim((string) $this->option('wh') ?: StockService::DEFAULT_WH_CODE);
        $dryRun = (bool) $this->option('dry-run');

        if ($from === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $this->error('Provide --from=YYYY-MM-DD.');

            return self::FAILURE;
        }

        $item = Item::query()->where('code', $itemCode)->first();
        if (! $item) {
            $this->error("Item [{$itemCode}] was not found.");

            return self::FAILURE;
        }

        $rowCount = StockBalance::query()
            ->where('item_id', $item->id)
            ->where('wh_code', $whCode)
            ->whereDate('date', '>=', $from)
            ->count();

        $lastEnd = StockBalance::query()
            ->where('item_id', $item->id)
            ->where('wh_code', $whCode)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('end');

        $onHand = $stockService->currentBalance((int) $item->id, $whCode);

        $this->info(sprintf(
            'Item %s (#%d) WH %s from %s: %d ledger row(s), last end %s, on-hand %s',
            $item->code,
            $item->id,
            $whCode,
            $from,
            $rowCount,
            number_format((float) ($lastEnd ?? 0), 2, '.', ''),
            number_format($onHand, 2, '.', ''),
        ));

        if ($dryRun) {
            $this->warn('Dry-run complete. No rows were updated.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(fn (): int => $stockService->rechainItemLedger(
            itemId: (int) $item->id,
            whCode: $whCode,
            fromDate: $from,
        ));

        $newLastEnd = StockBalance::query()
            ->where('item_id', $item->id)
            ->where('wh_code', $whCode)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('end');

        $this->info(sprintf(
            'Rechained %d row(s). Last end is now %s (on-hand unchanged at %s).',
            $updated,
            number_format((float) ($newLastEnd ?? 0), 2, '.', ''),
            number_format($onHand, 2, '.', ''),
        ));

        return self::SUCCESS;
    }
}
