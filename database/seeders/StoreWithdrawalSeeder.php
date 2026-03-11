<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyDepartmentLookup;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class StoreWithdrawalSeeder extends Seeder
{
    use ResolvesLegacyDepartmentLookup;
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $swsRows = $this->loadRows('sws');
        $swsDetailRows = $this->loadRows('sws_detail');

        if (empty($swsRows)) {
            $this->warn('No SWS rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();
        $this->prepareLegacyDepartmentLookup();
        $defaultUserId = $this->resolveLegacyFallbackUserId(2);

        $itemIdByCode = $this->buildItemLookup();

        $detailsBySwsCode = [];
        foreach ($swsDetailRows as $detailRow) {
            $swsCode = $this->normalizeValue($detailRow['sws_code'] ?? null);
            if ($swsCode === null) {
                continue;
            }

            $detailsBySwsCode[$this->normalizeSwsCodeKey($swsCode)][] = $detailRow;
        }

        $headerInserted = 0;
        $headerSkipped = 0;
        $detailInserted = 0;
        $detailSkipped = 0;

        $identityInsertEnabled = false;
        if ($this->isSqlServer()) {
            $this->setStoreWithdrawalItemsIdentityInsert(true);
            $identityInsertEnabled = true;
        }

        try {
            foreach ($swsRows as $swsRow) {
            $swsNumber = $this->normalizeValue($swsRow['sws_code'] ?? null);
            if ($swsNumber === null) {
                $headerSkipped++;
                continue;
            }

            $departmentCode = $this->normalizeValue($swsRow['department_code'] ?? null);
            $departmentId = $this->resolveLegacyDepartmentId($departmentCode);

            if ($departmentId === null) {
                $this->warn("SWS skipped: department_code not found for sws_code {$swsNumber}");
                $headerSkipped++;
                continue;
            }

            $rawInfo = $this->normalizeValue($swsRow['sws_info'] ?? null);
            [$type, $cleanInfo] = $this->extractTypeAndInfo($rawInfo);

            $swsDate = $this->parseDate($swsRow['sws_date'] ?? null) ?? now();
            $createdAt = $this->parseDate($swsRow['created_date'] ?? null) ?? $swsDate;
            $updatedAt = $this->parseDate($swsRow['updated_date'] ?? null) ?? $createdAt;
            $approvedAt = $this->parseDate($swsRow['approved_date'] ?? null);

            $createdById = $this->resolveLegacyUserId($swsRow['created_by'] ?? null, $defaultUserId, true, true);
            $updatedById = $this->resolveLegacyUserId($swsRow['updated_by'] ?? null, $defaultUserId, true, true);
            $approvedById = $this->resolveLegacyUserId($swsRow['approved_by'] ?? null, $defaultUserId, true, true);

            $isActive = ! $this->isNegative($swsRow['is_active'] ?? 'Y');

            $headerPayload = [
                'sws_date' => $swsDate,
                'department_id' => $departmentId,
                'department_code' => $departmentCode ?? '-',
                'type' => $type,
                'info' => $cleanInfo,
                'approved_by' => $approvedById,
                'approved_at' => $approvedAt,
                'created_by' => $createdById,
                'updated_by' => $updatedById,
                'meta' => json_encode([
                    'legacy_info_raw' => $rawInfo,
                    'legacy_created_by' => $this->normalizeValue($swsRow['created_by'] ?? null),
                    'legacy_updated_by' => $this->normalizeValue($swsRow['updated_by'] ?? null),
                    'legacy_approved_by' => $this->normalizeValue($swsRow['approved_by'] ?? null),
                ]),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $isActive ? null : $updatedAt,
            ];

            $detailRows = $detailsBySwsCode[$this->normalizeSwsCodeKey($swsNumber)] ?? [];

            $persistStoreWithdrawal = function () use (
                $swsNumber,
                $headerPayload,
                $detailRows,
                $itemIdByCode,
                $defaultUserId,
                $createdAt,
                $updatedAt,
                &$detailInserted,
                &$detailSkipped
            ): void {
                DB::table('store_withdrawals')->updateOrInsert(
                    ['sws_number' => $swsNumber],
                    ['sws_number' => $swsNumber] + $headerPayload
                );

                $storeWithdrawalId = DB::table('store_withdrawals')
                    ->where('sws_number', $swsNumber)
                    ->value('id');

                if (! $storeWithdrawalId) {
                    return;
                }

                DB::table('store_withdrawal_items')
                    ->where('store_withdrawal_id', $storeWithdrawalId)
                    ->delete();

                foreach ($detailRows as $detailRow) {
                    $legacyDetailId = $this->normalizeInteger($detailRow['id'] ?? null);
                    if ($legacyDetailId === null) {
                        $detailSkipped++;
                        continue;
                    }

                    $quantity = $this->normalizeDecimal($detailRow['qty'] ?? 0);
                    if ($quantity <= 0) {
                        $detailSkipped++;
                        continue;
                    }

                    $productCode = $this->normalizeValue($detailRow['product_code'] ?? null);
                    $itemId = $this->resolveByCode($itemIdByCode, $productCode);

                    $detailCreatedAt = $this->parseDate($detailRow['created_date'] ?? null) ?? $createdAt;
                    $detailUpdatedAt = $this->parseDate($detailRow['updated_date'] ?? null) ?? $updatedAt;

                    $detailCreatedBy = $this->resolveLegacyUserId(
                        $detailRow['created_by'] ?? null,
                        $defaultUserId,
                        true,
                        true
                    );

                    $detailUpdatedBy = $this->resolveLegacyUserId(
                        $detailRow['updated_by'] ?? null,
                        $defaultUserId,
                        true,
                        true
                    );

                    $detailIsActive = ! $this->isNegative($detailRow['is_active'] ?? 'Y');

                    $detailPayload = [
                        'store_withdrawal_id' => (int) $storeWithdrawalId,
                        'item_id' => $itemId,
                        'product_code' => $productCode,
                        'quantity' => round($quantity, 3),
                        'stock_on_hand_snapshot' => round($this->normalizeDecimal($detailRow['soh'] ?? 0), 3),
                        'uom' => $this->normalizeValue($detailRow['uom'] ?? null),
                        'created_by' => $detailCreatedBy,
                        'updated_by' => $detailUpdatedBy,
                        'meta' => json_encode([
                            'legacy_id' => $legacyDetailId,
                        ]),
                        'created_at' => $detailCreatedAt,
                        'updated_at' => $detailUpdatedAt,
                        'deleted_at' => $detailIsActive ? null : $detailUpdatedAt,
                    ];

                    $exists = DB::table('store_withdrawal_items')
                        ->where('id', $legacyDetailId)
                        ->exists();

                    if ($exists) {
                        DB::table('store_withdrawal_items')
                            ->where('id', $legacyDetailId)
                            ->update($detailPayload);
                    } else {
                        DB::table('store_withdrawal_items')->insert([
                            'id' => $legacyDetailId,
                        ] + $detailPayload);
                    }

                    $detailInserted++;
                }
            };

            if ($this->isSqlServer()) {
                $this->runWithSqlServerReconnect($persistStoreWithdrawal, "sws_code {$swsNumber}");
            } else {
                DB::transaction($persistStoreWithdrawal);
            }

            $headerInserted++;
        }
        } finally {
            if ($identityInsertEnabled) {
                $this->setStoreWithdrawalItemsIdentityInsert(false);
            }
        }

        $this->command?->info("✓ [sws] Inserted/Updated: {$headerInserted}, Skipped: {$headerSkipped}");
        $this->command?->info("✓ [sws_detail] Inserted: {$detailInserted}, Skipped: {$detailSkipped}");
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

    /**
     * @return array<string, int>
     */
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
     * @return array{0: string, 1: ?string}
     */
    private function extractTypeAndInfo(?string $info): array
    {
        if ($info === null) {
            return ['normal', null];
        }

        if (preg_match('/CONFIRMATORY\s*;/i', $info) !== 1) {
            return ['normal', $info];
        }

        $clean = preg_replace('/CONFIRMATORY\s*;\s*/i', '', $info);
        $clean = $this->normalizeValue($clean);

        return ['confirmatory', $clean];
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

    private function normalizeSwsCodeKey(string $value): string
    {
        $normalized = $this->normalizeLookupText($value);
        $trimmed = ltrim($normalized, '0');

        return $trimmed === '' ? '0' : $trimmed;
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

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

    private function setStoreWithdrawalItemsIdentityInsert(bool $enabled): void
    {
        $state = $enabled ? 'ON' : 'OFF';

        // Use unprepared for SQL Server session-level IDENTITY_INSERT state.
        DB::unprepared("SET IDENTITY_INSERT [store_withdrawal_items] {$state}");
    }

    private function runWithSqlServerReconnect(callable $callback, string $context): void
    {
        try {
            $callback();
            return;
        } catch (\Throwable $e) {
            if (! $this->isCommunicationLinkFailure($e)) {
                throw $e;
            }

            $this->warn("SQL Server communication link failure detected while importing {$context}, retrying once...");

            DB::disconnect();
            DB::reconnect();
        }

        $callback();
    }

    private function isCommunicationLinkFailure(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'communication link failure')
            || str_contains($message, 'sqlstate[08s01]')
            || str_contains($message, 'connection is no longer usable');
    }

    private function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[StoreWithdrawalSeeder] {$message}");
    }
}
