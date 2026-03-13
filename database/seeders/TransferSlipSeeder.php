<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TransferSlipSeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tsRows = $this->loadRows('ts');
        $tsDetailRows = $this->loadRows('ts_detail');

        if (empty($tsRows)) {
            $this->warn('No transfer slip rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();

        $defaultUserId = $this->resolveLegacyFallbackUserId(2);
        $storeWithdrawalIdByNumber = $this->buildCodeLookup(
            DB::table('store_withdrawals')->pluck('id', 'sws_number')->all()
        );
        $itemIdByCode = $this->buildItemLookup();
        $storeWithdrawalItemCandidates = $this->buildStoreWithdrawalItemCandidates();

        $detailsByTsCode = [];
        foreach ($tsDetailRows as $detailRow) {
            $tsCode = $this->normalizeValue($detailRow['ts_code'] ?? null);
            if ($tsCode === null) {
                continue;
            }

            $detailsByTsCode[$tsCode][] = $detailRow;
        }

        $headerInserted = 0;
        $headerSkipped = 0;
        $detailInserted = 0;
        $detailSkipped = 0;

        foreach ($tsRows as $tsRow) {
            $tsNumber = $this->normalizeValue($tsRow['ts_code'] ?? null);
            if ($tsNumber === null) {
                $headerSkipped++;
                continue;
            }

            $legacySwsCode = $this->normalizeValue($tsRow['sws_code'] ?? null);
            $storeWithdrawalId = $this->resolveByCode($storeWithdrawalIdByNumber, $legacySwsCode);

            if ($storeWithdrawalId === null) {
                $headerSkipped++;
                $this->warn("TS skipped: sws_code not found in store_withdrawals for ts_code {$tsNumber}");
                continue;
            }

            $tsDate = $this->parseDate($tsRow['ts_date'] ?? null) ?? now();
            $createdAt = $this->parseDate($tsRow['created_date'] ?? null) ?? $tsDate;
            $updatedAt = $this->parseDate($tsRow['updated_date'] ?? null) ?? $createdAt;

            $createdById = $this->resolveLegacyUserId($tsRow['created_by'] ?? null, $defaultUserId) ?? $defaultUserId;
            $updatedById = $this->resolveLegacyUserId($tsRow['updated_by'] ?? null, $defaultUserId, true, true);
            $notedById = $this->resolveLegacyUserId($tsRow['noted_by'] ?? null, $defaultUserId, true, true);
            $approvedById = $this->resolveLegacyUserId($tsRow['approved_by'] ?? null, $defaultUserId, true, true);
            $receivedById = $this->resolveLegacyUserId($tsRow['received_by'] ?? null, $defaultUserId, true, true);

            $notedAt = $this->parseDate($tsRow['noted_date'] ?? null);
            $approvedAt = $this->parseDate($tsRow['approved_date'] ?? null);
            $receivedAt = $this->parseDate($tsRow['received_date'] ?? null);
            $isActive = ! $this->isNegative($tsRow['is_active'] ?? 'Y');

            $headerPayload = [
                'ts_date' => $tsDate,
                'store_withdrawal_id' => $storeWithdrawalId,
                'for_production' => $this->detectForProduction($tsRow),
                'remarks' => $this->normalizeValue($tsRow['ts_info'] ?? null),
                'transfer_to' => $this->normalizeValue($tsRow['ts_to'] ?? null),
                'noted_by' => $notedById,
                'noted_at' => $notedAt,
                'approved_by' => $approvedById,
                'approved_at' => $approvedAt,
                'received_by' => $receivedById,
                'received_at' => $receivedAt,
                'created_by' => $createdById,
                'updated_by' => $updatedById ?? $createdById,
                'meta' => json_encode([
                    'legacy_sws_code' => $legacySwsCode,
                    'legacy_ts_module' => $this->normalizeValue($tsRow['ts_module'] ?? null),
                    'legacy_ts_type' => $this->normalizeValue($tsRow['ts_type'] ?? null),
                    'legacy_noted_by' => $this->normalizeValue($tsRow['noted_by'] ?? null),
                    'legacy_approved_by' => $this->normalizeValue($tsRow['approved_by'] ?? null),
                    'legacy_received_by' => $this->normalizeValue($tsRow['received_by'] ?? null),
                ]),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $isActive ? null : $updatedAt,
            ];

            $detailRows = $detailsByTsCode[$tsNumber] ?? [];

            DB::transaction(function () use (
                $tsNumber,
                $headerPayload,
                $detailRows,
                $storeWithdrawalId,
                $itemIdByCode,
                $storeWithdrawalItemCandidates,
                $createdAt,
                $updatedAt,
                $defaultUserId,
                &$detailInserted,
                &$detailSkipped
            ): void {
                DB::table('transfer_slips')->updateOrInsert(
                    ['ts_number' => $tsNumber],
                    ['ts_number' => $tsNumber] + $headerPayload
                );

                $transferSlipId = DB::table('transfer_slips')
                    ->where('ts_number', $tsNumber)
                    ->value('id');

                if (! $transferSlipId) {
                    return;
                }

                DB::table('transfer_slip_items')
                    ->where('transfer_slip_id', $transferSlipId)
                    ->delete();

                foreach ($detailRows as $detailRow) {
                    $itemId = $this->resolveByCode($itemIdByCode, $detailRow['product_code'] ?? null);
                    if ($itemId === null) {
                        $detailSkipped++;
                        continue;
                    }

                    $storeWithdrawalItemId = $this->resolveStoreWithdrawalItemId(
                        $storeWithdrawalId,
                        $itemId,
                        $detailRow['product_code'] ?? null,
                        $storeWithdrawalItemCandidates,
                    );

                    $quantity = $this->normalizeDecimal($detailRow['qty'] ?? 0);
                    if ($quantity <= 0) {
                        $detailSkipped++;
                        continue;
                    }

                    $detailCreatedAt = $this->parseDate($detailRow['created_date'] ?? null) ?? $createdAt;
                    $detailUpdatedAt = $this->parseDate($detailRow['updated_date'] ?? null) ?? $updatedAt;
                    $detailCreatedById = $this->resolveLegacyUserId($detailRow['created_by'] ?? null, $defaultUserId, true, true);
                    $detailUpdatedById = $this->resolveLegacyUserId($detailRow['updated_by'] ?? null, $defaultUserId, true, true);
                    $detailIsActive = ! $this->isNegative($detailRow['is_active'] ?? 'Y');

                    DB::table('transfer_slip_items')->insert([
                        'transfer_slip_id' => (int) $transferSlipId,
                        'store_withdrawal_item_id' => $storeWithdrawalItemId,
                        'item_id' => $itemId,
                        'product_code' => $this->normalizeValue($detailRow['product_code'] ?? null),
                        'quantity' => $quantity,
                        'created_by' => $detailCreatedById,
                        'updated_by' => $detailUpdatedById ?? $detailCreatedById,
                        'meta' => json_encode([
                            'legacy_detail_id' => $this->normalizeInteger($detailRow['id'] ?? null),
                            'legacy_created_by' => $this->normalizeValue($detailRow['created_by'] ?? null),
                            'legacy_updated_by' => $this->normalizeValue($detailRow['updated_by'] ?? null),
                        ]),
                        'created_at' => $detailCreatedAt,
                        'updated_at' => $detailUpdatedAt,
                        'deleted_at' => $detailIsActive ? null : $detailUpdatedAt,
                    ]);

                    $detailInserted++;
                }
            });

            $headerInserted++;
        }

        $this->command?->info("✓ [ts] Inserted/Updated: {$headerInserted}, Skipped: {$headerSkipped}");
        $this->command?->info("✓ [ts_detail] Inserted: {$detailInserted}, Skipped: {$detailSkipped}");
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @param  array<string, int>  $pairs
     * @return array<string, int>
     */
    private function buildCodeLookup(array $pairs): array
    {
        $lookup = [];

        foreach ($pairs as $code => $id) {
            $normalized = $this->normalizeLookupText((string) $code);
            if ($normalized === '') {
                continue;
            }

            if (! isset($lookup[$normalized])) {
                $lookup[$normalized] = (int) $id;
            }

            $trimmed = ltrim($normalized, '0');
            if ($trimmed !== '' && ! isset($lookup[$trimmed])) {
                $lookup[$trimmed] = (int) $id;
            }
        }

        return $lookup;
    }

    private function buildItemLookup(): array
    {
        $lookup = $this->buildCodeLookup(DB::table('items')->pluck('id', 'code')->all());

        if (Schema::hasColumn('items', 'product_code')) {
            $productCodeLookup = $this->buildCodeLookup(DB::table('items')->pluck('id', 'product_code')->all());

            foreach ($productCodeLookup as $code => $id) {
                if (! isset($lookup[$code])) {
                    $lookup[$code] = $id;
                }
            }
        }

        return $lookup;
    }

    /**
     * @return array<int, array<int, array<int, int>>>
     */
    private function buildStoreWithdrawalItemCandidates(): array
    {
        $candidates = [];

        $rows = DB::table('store_withdrawal_items')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select(['id', 'store_withdrawal_id', 'item_id', 'product_code'])
            ->get();

        foreach ($rows as $row) {
            $storeWithdrawalId = (int) $row->store_withdrawal_id;
            $itemId = (int) $row->item_id;

            if ($storeWithdrawalId <= 0 || $itemId <= 0) {
                continue;
            }

            $candidates[$storeWithdrawalId][$itemId][] = (int) $row->id;

            $productCode = $this->normalizeValue($row->product_code ?? null);
            if ($productCode !== null) {
                $normalizedCode = $this->normalizeLookupText($productCode);
                $candidates[$storeWithdrawalId][$normalizedCode][] = (int) $row->id;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<int, array<int|string, array<int, int>>>  $candidates
     */
    private function resolveStoreWithdrawalItemId(int $storeWithdrawalId, int $itemId, mixed $productCode, array $candidates): ?int
    {
        $byItem = $candidates[$storeWithdrawalId][$itemId] ?? [];
        if (! empty($byItem)) {
            return (int) $byItem[0];
        }

        $normalizedCode = $this->normalizeValue($productCode);
        if ($normalizedCode === null) {
            return null;
        }

        $byCode = $candidates[$storeWithdrawalId][$this->normalizeLookupText($normalizedCode)] ?? [];

        return ! empty($byCode) ? (int) $byCode[0] : null;
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

        $normalized = $this->normalizeLookupText($code);
        if (isset($codeLookup[$normalized])) {
            return $codeLookup[$normalized];
        }

        $trimmed = ltrim($normalized, '0');
        if ($trimmed !== '' && isset($codeLookup[$trimmed])) {
            return $codeLookup[$trimmed];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tsRow
     */
    private function detectForProduction(array $tsRow): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $this->normalizeValue($tsRow['ts_module'] ?? null),
            $this->normalizeValue($tsRow['ts_type'] ?? null),
            $this->normalizeValue($tsRow['ts_to'] ?? null),
            $this->normalizeValue($tsRow['ts_info'] ?? null),
        ])));

        return str_contains($haystack, 'production');
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }

    private function normalizeLookupText(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeDecimal(mixed $value): float
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            return 0.0;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null || ! is_numeric($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $normalized = $this->normalizeValue($value);
        if ($normalized === null) {
            return null;
        }

        try {
            $date = Carbon::parse($normalized);

            if ($date->year < 1970) {
                return null;
            }

            return $date;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isNegative(mixed $value): bool
    {
        $normalized = strtoupper((string) ($this->normalizeValue($value) ?? ''));
        return in_array($normalized, ['N', 'NO', '0', 'FALSE', 'F'], true);
    }

    private function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[TransferSlipSeeder] {$message}");
    }
}
