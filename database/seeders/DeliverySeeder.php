<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeliverySeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drRows = $this->loadRows('dr');
        $drDetailRows = $this->loadRows('dr_detail');

        if (empty($drRows)) {
            $this->warn('No delivery rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();

        $defaultUserId = $this->resolveLegacyFallbackUserId(2);
        $itemIdByCode = $this->buildItemLookup();
        $supplierInfoByCode = $this->buildSupplierLookup();

        $detailsByDrCode = [];
        foreach ($drDetailRows as $detailRow) {
            $drCode = $this->normalizeValue($detailRow['dr_code'] ?? null);
            if ($drCode === null) {
                continue;
            }

            $detailsByDrCode[$this->normalizeLookupText($drCode)][] = $detailRow;
        }

        $headerInserted = 0;
        $headerSkipped = 0;
        $detailInserted = 0;
        $detailSkipped = 0;

        foreach ($drRows as $drRow) {
            $drNumber = $this->normalizeValue($drRow['dr_code'] ?? null);
            if ($drNumber === null) {
                $headerSkipped++;
                continue;
            }

            $drDate = $this->parseDate($drRow['dr_date'] ?? null) ?? now();
            $createdAt = $this->parseDate($drRow['created_date'] ?? null) ?? $drDate;
            $updatedAt = $this->parseDate($drRow['updated_date'] ?? null) ?? $createdAt;

            $createdById = $this->resolveLegacyUserId($drRow['created_by'] ?? null, $defaultUserId, true, true);
            $updatedById = $this->resolveLegacyUserId($drRow['updated_by'] ?? null, $defaultUserId, true, true);
            $isActive = ! $this->isNegative($drRow['is_active'] ?? 'Y');

            $supplierCode = $this->normalizeValue($drRow['supplier_code'] ?? null);
            $supplierInfo = $this->resolveSupplier($supplierCode, $supplierInfoByCode);

            $headerPayload = [
                'dr_date' => $drDate,
                'from_name' => $this->normalizeValue($drRow['dr_from'] ?? null) ?? 'IM - PT. SPFI',
                'from_location' => $this->normalizeValue($drRow['dr_fromloc'] ?? null),
                'supplier_id' => $supplierInfo['id'] ?? null,
                'to_location' => $this->normalizeValue($drRow['dr_toloc'] ?? null),
                'remarks' => $this->normalizeValue($drRow['dr_remarks'] ?? null),
                'or_number' => $this->normalizeValue($drRow['or_code'] ?? null),
                'dm_number' => $this->normalizeValue($drRow['dm_code'] ?? null),
                'created_by' => $createdById,
                'updated_by' => $updatedById,
                'meta' => json_encode([
                    'legacy_dr_type' => $this->normalizeValue($drRow['dr_type'] ?? null),
                    'legacy_supplier_code' => $supplierCode,
                    'legacy_delivered_by' => $this->normalizeValue($drRow['delivered_by'] ?? null),
                    'legacy_delivered_date' => $this->normalizeValue($drRow['delivered_date'] ?? null),
                    'legacy_approved_by' => $this->normalizeValue($drRow['approved_by'] ?? null),
                    'legacy_approved_date' => $this->normalizeValue($drRow['approved_date'] ?? null),
                    'legacy_received_by' => $this->normalizeValue($drRow['received_by'] ?? null),
                    'legacy_received_date' => $this->normalizeValue($drRow['received_date'] ?? null),
                    'legacy_is_bc' => $this->normalizeValue($drRow['Is_BC'] ?? null),
                ]),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $isActive ? null : $updatedAt,
            ];

            $detailRows = $detailsByDrCode[$this->normalizeLookupText($drNumber)] ?? [];

            DB::transaction(function () use (
                $drNumber,
                $headerPayload,
                $detailRows,
                $itemIdByCode,
                $defaultUserId,
                $createdAt,
                $updatedAt,
                &$detailInserted,
                &$detailSkipped
            ): void {
                DB::table('deliveries')->updateOrInsert(
                    ['dr_number' => $drNumber],
                    ['dr_number' => $drNumber] + $headerPayload
                );

                $deliveryId = DB::table('deliveries')
                    ->where('dr_number', $drNumber)
                    ->value('id');

                if (! $deliveryId) {
                    return;
                }

                DB::table('delivery_items')
                    ->where('delivery_id', $deliveryId)
                    ->delete();

                foreach ($detailRows as $detailRow) {
                    $quantity = $this->normalizeDecimal($detailRow['dr_qty'] ?? 0);
                    if ($quantity <= 0) {
                        $detailSkipped++;
                        continue;
                    }

                    $productCode = $this->normalizeValue($detailRow['product_code'] ?? null);
                    $itemId = $this->resolveByCode($itemIdByCode, $productCode);

                    $detailCreatedAt = $this->parseDate($detailRow['created_date'] ?? null) ?? $createdAt;
                    $detailUpdatedAt = $this->parseDate($detailRow['updated_date'] ?? null) ?? $updatedAt;
                    $detailCreatedBy = $this->resolveLegacyUserId($detailRow['created_by'] ?? null, $defaultUserId, true, true);
                    $detailUpdatedBy = $this->resolveLegacyUserId($detailRow['updated_by'] ?? null, $defaultUserId, true, true);
                    $detailIsActive = ! $this->isNegative($detailRow['is_active'] ?? 'Y');

                    DB::table('delivery_items')->insert([
                        'delivery_id' => (int) $deliveryId,
                        'item_id' => $itemId,
                        'product_code' => $productCode,
                        'uom' => $this->normalizeValue($detailRow['dr_uom'] ?? null),
                        'quantity' => round($quantity, 3),
                        'created_by' => $detailCreatedBy,
                        'updated_by' => $detailUpdatedBy,
                        'meta' => json_encode([
                            'legacy_detail_id' => $this->normalizeInteger($detailRow['id'] ?? null),
                            'legacy_unit_cost' => $this->normalizeValue($detailRow['dr_unitcost'] ?? null),
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

        $this->command?->info("✓ [dr] Inserted/Updated: {$headerInserted}, Skipped: {$headerSkipped}");
        $this->command?->info("✓ [dr_detail] Inserted: {$detailInserted}, Skipped: {$detailSkipped}");
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
     * @return array<string, array{id:int,address:?string}>
     */
    private function buildSupplierLookup(): array
    {
        $lookup = [];

        $rows = DB::table('suppliers')
            ->select(['id', 'code', 'address'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $code = $this->normalizeValue($row->code ?? null);

            if ($code === null) {
                continue;
            }

            $lookup[$this->normalizeLookupText($code)] = [
                'id' => (int) $row->id,
                'address' => $this->normalizeValue($row->address ?? null),
            ];
        }

        return $lookup;
    }

    /**
     * @param  array<string, array{id:int,address:?string}>  $supplierInfoByCode
     * @return array{id:int,address:?string}|null
     */
    private function resolveSupplier(?string $supplierCode, array $supplierInfoByCode): ?array
    {
        if ($supplierCode === null) {
            return null;
        }

        $normalized = $this->normalizeLookupText($supplierCode);
        if (isset($supplierInfoByCode[$normalized])) {
            return $supplierInfoByCode[$normalized];
        }

        $trimmed = ltrim($normalized, '0');
        if ($trimmed !== '' && isset($supplierInfoByCode[$trimmed])) {
            return $supplierInfoByCode[$trimmed];
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

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || strtoupper($value) === 'NULL' || strtolower($value) === 'undefined') {
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
        Log::warning("[DeliverySeeder] {$message}");
    }
}
