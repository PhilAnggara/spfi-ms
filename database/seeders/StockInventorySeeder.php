<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockInventorySeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadRows('stock_inventory');

        if (empty($rows)) {
            $this->warn('No stock_inventory rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();
        $defaultUserId = $this->resolveLegacyFallbackUserId(2);

        $itemIdByCode = $this->buildCodeLookup(DB::table('items')->pluck('id', 'code')->all());
        $validItemIds = DB::table('items')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all();

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $productCode = $this->firstNormalizedValue($row, ['product_code', 'productcode', 'item_code', 'code']);
            $itemId = $this->resolveItemId($row, $productCode, $itemIdByCode, $validItemIds);

            if ($itemId === null) {
                $skipped++;
                continue;
            }

            $whCode = $this->firstNormalizedValue($row, ['wh_code', 'warehouse_code', 'warehouse', 'wh']) ?? 'MAIN';
            $balance = $this->firstDecimal($row, ['balance', 'end', 'end_qty', 'qty', 'stock', 'stock_qty'], 0);
            $startBalance = $this->firstDecimal($row, ['start_balance', 'begin', 'begin_qty', 'opening_balance'], 0);
            $averagePrice = $this->firstDecimal($row, ['average_price', 'avg_price', 'unit_price', 'price'], 0);

            $isActive = ! $this->isNegative($this->firstNormalizedValue($row, ['is_active']) ?? 'Y');
            $isDelete = $this->isAffirmative($this->firstNormalizedValue($row, ['is_delete']) ?? 'N');

            $createdAt = $this->firstDate($row, ['created_date', 'created_at']) ?? now();
            $updatedAt = $this->firstDate($row, ['updated_date', 'updated_at']) ?? $createdAt;

            $createdBy = $this->resolveLegacyUserId(
                $this->firstNormalizedValue($row, ['created_by', 'createdby']),
                $defaultUserId,
            );
            $updatedBy = $this->resolveLegacyUserId(
                $this->firstNormalizedValue($row, ['updated_by', 'updatedby']),
                $defaultUserId,
            );

            DB::table('stock_inventories')->updateOrInsert(
                [
                    'item_id' => $itemId,
                    'wh_code' => $whCode,
                ],
                [
                    'product_code' => $productCode ?? ($this->firstNormalizedValue($row, ['product_code', 'productcode']) ?? ''),
                    'balance' => round($balance, 2),
                    'start_balance' => round($startBalance, 2),
                    'average_price' => round($averagePrice, 2),
                    'is_active' => $isActive,
                    'is_delete' => $isDelete,
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]
            );

            $inserted++;
        }

        $this->syncItemStockOnHand();

        $this->command?->info("✓ [stock_inventory] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
    }

    private function syncItemStockOnHand(): void
    {
        DB::table('items')->update(['stock_on_hand' => 0]);

        $totals = DB::table('stock_inventories')
            ->selectRaw('item_id, SUM(balance) AS total_balance')
            ->where('is_active', true)
            ->where('is_delete', false)
            ->groupBy('item_id')
            ->pluck('total_balance', 'item_id');

        foreach ($totals as $itemId => $totalBalance) {
            DB::table('items')
                ->where('id', (int) $itemId)
                ->update([
                    'stock_on_hand' => (int) round((float) $totalBalance),
                ]);
        }
    }

    private function loadRows(string $dataset): array
    {
        $legacyRows = $this->resolveRows($dataset, fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && ! empty($legacyRows)) {
            $this->logImportSource($dataset, 'legacy');
            $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($legacyRows));
            return $legacyRows;
        }

        $csvRows = $this->readCsvRows($dataset);

        if ($this->isLegacySource()) {
            $this->logImportSource($dataset, 'csv-fallback');
        } else {
            $this->logImportSource($dataset, 'csv');
        }

        $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($csvRows));

        return $csvRows;
    }

    private function readCsvRows(string $dataset): array
    {
        $csvPath = $this->csvPathFor($dataset);

        if (! file_exists($csvPath)) {
            $this->warn("CSV for dataset [{$dataset}] not found at {$csvPath}");
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->warn("Unable to open CSV for dataset [{$dataset}] at {$csvPath}");
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ';';
        if ($firstLine !== false && substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
            $delimiter = ',';
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(function ($value): string {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            return trim($value);
        }, $header);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), null);
            }

            if (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            }

            $combined = array_combine($header, $row);
            if ($combined === false) {
                continue;
            }

            $rows[] = $combined;
        }

        fclose($handle);

        return $rows;
    }

    private function resolveItemId(array $row, ?string $productCode, array $itemIdByCode, array $validItemIds): ?int
    {
        if ($productCode !== null) {
            $fromCode = $this->resolveByCode($itemIdByCode, $productCode);
            if ($fromCode !== null) {
                return $fromCode;
            }
        }

        $rawItemId = $this->firstNormalizedValue($row, ['item_id', 'itemid']);
        $itemId = $this->normalizeInteger($rawItemId);

        if ($itemId !== null && isset($validItemIds[$itemId])) {
            return $itemId;
        }

        return null;
    }

    private function firstNormalizedValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->normalizeValue($row[$key]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function firstDecimal(array $row, array $keys, float $default = 0): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->normalizeDecimal($row[$key]);
            return $value;
        }

        return $default;
    }

    private function firstDate(array $row, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->parseDate($row[$key]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function buildCodeLookup(array $rawLookup): array
    {
        $lookup = [];

        foreach ($rawLookup as $rawCode => $value) {
            $code = $this->normalizeValue($rawCode);
            $id = $this->normalizeInteger($value);

            if ($code === null || $id === null) {
                continue;
            }

            $lookup[$code] = $id;
            $numericCode = ltrim($code, '0');
            if ($numericCode !== '' && ! isset($lookup[$numericCode])) {
                $lookup[$numericCode] = $id;
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, int>  $codeLookup
     */
    private function resolveByCode(array $codeLookup, mixed $rawCode): ?int
    {
        $code = $this->normalizeValue($rawCode);
        if ($code === null) {
            return null;
        }

        if (isset($codeLookup[$code])) {
            return $codeLookup[$code];
        }

        $numericCode = ltrim($code, '0');
        if ($numericCode !== '' && isset($codeLookup[$numericCode])) {
            return $codeLookup[$numericCode];
        }

        return null;
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtoupper($normalized) === 'NULL') {
            return null;
        }

        return $normalized;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizeDecimal(mixed $value): float
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return Carbon::parse($normalized);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeLookupText(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function isAffirmative(mixed $value): bool
    {
        $normalized = strtoupper((string) ($this->normalizeValue($value) ?? ''));
        return in_array($normalized, ['Y', 'YES', 'TRUE', '1'], true);
    }

    private function isNegative(mixed $value): bool
    {
        $normalized = strtoupper((string) ($this->normalizeValue($value) ?? ''));
        return in_array($normalized, ['N', 'NO', 'FALSE', '0'], true);
    }

    private function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[StockInventorySeeder] {$message}");
    }
}
