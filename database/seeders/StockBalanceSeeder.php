<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockBalanceSeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadRows('stock_balance');

        if (empty($rows)) {
            $this->warn('No stock_balance rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();
        $defaultUserId = $this->resolveLegacyFallbackUserId(2);

        $itemIdByCode = $this->buildCodeLookup(DB::table('items')->pluck('id', 'code')->all());
        $validItemIds = DB::table('items')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all();

        DB::table('stock_balances')->delete();

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $productCode = $this->firstNormalizedValue($row, ['product_code', 'productcode', 'item_code', 'code']);
            $itemId = $this->resolveItemId($row, $productCode, $itemIdByCode, $validItemIds);

            if ($itemId === null) {
                $skipped++;
                continue;
            }

            $date = $this->firstDate($row, ['date', 'trx_date', 'transaction_date', 'movement_date']);
            if ($date === null) {
                $skipped++;
                continue;
            }

            $whCode = $this->firstNormalizedValue($row, ['wh_code', 'warehouse_code', 'warehouse', 'wh']) ?? 'MAIN';

            $begin = $this->firstDecimal($row, ['begin', 'begin_qty', 'opening_balance', 'start_balance'], 0);
            $qtyIn1 = $this->firstDecimal($row, ['qty_in1', 'qtyin1'], 0);
            $qtyIn2 = $this->firstDecimal($row, ['qty_in2', 'qtyin2'], 0);
            $qtyIn3 = $this->firstDecimal($row, ['qty_in3', 'qtyin3'], 0);
            $qtyOut1 = $this->firstDecimal($row, ['qty_out1', 'qtyout1'], 0);
            $qtyOut2 = $this->firstDecimal($row, ['qty_out2', 'qtyout2'], 0);
            $qtyOut3 = $this->firstDecimal($row, ['qty_out3', 'qtyout3'], 0);

            $computedEnd = $begin + $qtyIn1 + $qtyIn2 + $qtyIn3 - $qtyOut1 - $qtyOut2 - $qtyOut3;
            $end = $this->firstDecimal($row, ['end', 'end_qty', 'closing_balance'], $computedEnd);

            $accQtyIn1 = $this->firstDecimal($row, ['acc_qty_in1', 'accqtyin1'], $qtyIn1);
            $accAvgIn1 = $this->firstDecimal($row, ['acc_average_price_in1', 'accavgpricein1'], 0);
            $accQtyTotal = $this->firstDecimal($row, ['acc_qty_total', 'accqtytotal'], $end);
            $accAvgTotal = $this->firstDecimal($row, ['acc_average_price_total', 'accavgpricetotal'], 0);

            $referenceType = $this->firstNormalizedValue($row, ['reference_type', 'ref_type']);
            $referenceId = $this->firstInteger($row, ['reference_id', 'ref_id']);
            $referenceLineId = $this->firstInteger($row, ['reference_line_id', 'ref_line_id', 'reference_item_id']);

            $createdAt = $this->firstDate($row, ['created_date', 'created_at']) ?? $date;
            $updatedAt = $this->firstDate($row, ['updated_date', 'updated_at']) ?? $createdAt;

            $createdBy = $this->resolveLegacyUserId(
                $this->firstNormalizedValue($row, ['created_by', 'createdby']),
                $defaultUserId,
            );

            DB::table('stock_balances')->insert([
                'date' => $date->toDateString(),
                'item_id' => $itemId,
                'product_code' => $productCode ?? ($this->firstNormalizedValue($row, ['product_code', 'productcode']) ?? ''),
                'wh_code' => $whCode,
                'begin' => round($begin, 2),
                'qty_in1' => round($qtyIn1, 2),
                'qty_in2' => round($qtyIn2, 2),
                'qty_in3' => round($qtyIn3, 2),
                'qty_out1' => round($qtyOut1, 2),
                'qty_out2' => round($qtyOut2, 2),
                'qty_out3' => round($qtyOut3, 2),
                'end' => round($end, 2),
                'acc_qty_in1' => round($accQtyIn1, 2),
                'acc_average_price_in1' => round($accAvgIn1, 2),
                'acc_qty_total' => round($accQtyTotal, 2),
                'acc_average_price_total' => round($accAvgTotal, 2),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_line_id' => $referenceLineId,
                'created_by' => $createdBy,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $inserted++;
        }

        $this->command?->info("✓ [stock_balance] Inserted: {$inserted}, Skipped: {$skipped}");
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

            return $this->normalizeDecimal($row[$key]);
        }

        return $default;
    }

    private function firstInteger(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $this->normalizeInteger($row[$key]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
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
        Log::warning("[StockBalanceSeeder] {$message}");
    }
}
