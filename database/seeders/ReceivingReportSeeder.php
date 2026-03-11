<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivingReportSeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rrRows = $this->loadRows('rr');
        $rrDetailRows = $this->loadRows('rr_detail');

        if (empty($rrRows)) {
            $this->warn('No receiving report rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup();

        $defaultUserId = $this->resolveLegacyFallbackUserId(2);
        $defaultCustomsDocumentTypeId = $this->resolveDefaultCustomsDocumentTypeId();

        $purchaseOrderIdByNumber = $this->buildCodeLookup(
            DB::table('purchase_orders')->pluck('id', 'po_number')->all()
        );
        $itemIdByCode = $this->buildCodeLookup(
            DB::table('items')->pluck('id', 'code')->all()
        );

        $poItemCandidates = $this->buildPurchaseOrderItemCandidates();

        $detailsByRrCode = [];
        foreach ($rrDetailRows as $detailRow) {
            $rrCode = $this->normalizeValue($detailRow['rr_code'] ?? null);
            if ($rrCode === null) {
                continue;
            }

            $detailsByRrCode[$rrCode][] = $detailRow;
        }

        $headerInserted = 0;
        $headerSkipped = 0;
        $itemInserted = 0;
        $itemSkipped = 0;
        $itemMissingPo = 0;

        foreach ($rrRows as $rrRow) {
            $rrNumber = $this->normalizeValue($rrRow['rr_code'] ?? null);
            if ($rrNumber === null) {
                $headerSkipped++;
                continue;
            }

            $legacyPoCode = $this->normalizeValue($rrRow['po_code'] ?? null);
            $purchaseOrderId = $this->resolveByCode($purchaseOrderIdByNumber, $legacyPoCode);

            if ($purchaseOrderId === null) {
                $headerSkipped++;
                $this->warn("RR skipped: po_code not found in purchase_orders for rr_code {$rrNumber}");
                continue;
            }

            $receivedDate = $this->parseDate($rrRow['rr_date'] ?? null) ?? now();
            $createdAt = $this->parseDate($rrRow['created_date'] ?? null) ?? $receivedDate;
            $updatedAt = $this->parseDate($rrRow['updated_date'] ?? null) ?? $createdAt;

            $createdById = $this->resolveLegacyUserId($rrRow['created_by'] ?? null, $defaultUserId) ?? $defaultUserId;
            $isActive = ! $this->isNegative($rrRow['is_active'] ?? 'Y');
            $requiresCustomsDocument = $this->isAffirmative($rrRow['Is_BC'] ?? null);

            $evaluatedDate = $this->parseDate($rrRow['evaluated_date'] ?? null);
            $approvedDate = $this->parseDate($rrRow['approved_date'] ?? null);

            $headerPayload = [
                'purchase_order_id' => $purchaseOrderId,
                'received_date' => $receivedDate,
                'requires_customs_document' => $requiresCustomsDocument,
                'customs_document_number' => null,
                'customs_document_type_id' => $requiresCustomsDocument ? $defaultCustomsDocumentTypeId : null,
                'customs_document_date' => null,
                'notes' => $this->normalizeValue($rrRow['rr_remarks'] ?? null),
                'meta' => json_encode([
                    'legacy_po_code' => $legacyPoCode,
                    'rr_from' => $this->normalizeValue($rrRow['rr_from'] ?? null),
                    'evaluated_by' => $this->normalizeValue($rrRow['evaluated_by'] ?? null),
                    'evaluated_date' => $evaluatedDate?->toDateTimeString(),
                    'approved_by' => $this->normalizeValue($rrRow['approved_by'] ?? null),
                    'approved_date' => $approvedDate?->toDateTimeString(),
                    'updated_by' => $this->normalizeValue($rrRow['updated_by'] ?? null),
                ]),
                'created_by' => $createdById,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $isActive ? null : $updatedAt,
            ];

            $detailRows = $detailsByRrCode[$rrNumber] ?? [];

            $persistReceivingReport = function () use (
                $rrNumber,
                $headerPayload,
                $detailRows,
                $purchaseOrderId,
                $itemIdByCode,
                $poItemCandidates,
                $createdAt,
                $updatedAt,
                &$itemInserted,
                &$itemSkipped,
                &$itemMissingPo
            ): void {
                DB::table('receiving_reports')->updateOrInsert(
                    ['rr_number' => $rrNumber],
                    ['rr_number' => $rrNumber] + $headerPayload
                );

                $receivingReportId = DB::table('receiving_reports')
                    ->where('rr_number', $rrNumber)
                    ->value('id');

                if (! $receivingReportId) {
                    return;
                }

                DB::table('receiving_report_items')
                    ->where('receiving_report_id', $receivingReportId)
                    ->delete();

                $payloads = $this->buildDetailPayloads(
                    detailRows: $detailRows,
                    purchaseOrderId: (int) $purchaseOrderId,
                    itemIdByCode: $itemIdByCode,
                    poItemCandidates: $poItemCandidates,
                    fallbackCreatedAt: $createdAt,
                    fallbackUpdatedAt: $updatedAt,
                    missingPoItemCount: $itemMissingPo,
                    skippedCount: $itemSkipped,
                );

                foreach ($payloads as $payload) {
                    DB::table('receiving_report_items')->insert([
                        'receiving_report_id' => (int) $receivingReportId,
                        'purchase_order_item_id' => $payload['purchase_order_item_id'],
                        'qty_good' => $payload['qty_good'],
                        'qty_bad' => $payload['qty_bad'],
                        'meta' => json_encode($payload['meta']),
                        'created_at' => $payload['created_at'],
                        'updated_at' => $payload['updated_at'],
                    ]);

                    $itemInserted++;
                }
            };

            if ($this->isSqlServer()) {
                $this->runWithSqlServerReconnect($persistReceivingReport, "rr_code {$rrNumber}");
            } else {
                DB::transaction($persistReceivingReport);
            }

            $headerInserted++;
        }

        $this->command?->info("✓ [rr] Inserted/Updated: {$headerInserted}, Skipped: {$headerSkipped}");
        $this->command?->info("✓ [rr_detail] Inserted: {$itemInserted}, Skipped: {$itemSkipped}, Missing PO item link: {$itemMissingPo}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildDetailPayloads(
        array $detailRows,
        int $purchaseOrderId,
        array $itemIdByCode,
        array $poItemCandidates,
        Carbon $fallbackCreatedAt,
        Carbon $fallbackUpdatedAt,
        int &$missingPoItemCount,
        int &$skippedCount,
    ): array {
        $usageByPoItemId = [];
        $grouped = [];

        foreach ($detailRows as $detailRow) {
            if ($this->isNegative($detailRow['is_active'] ?? 'Y')) {
                continue;
            }

            $itemId = $this->resolveByCode($itemIdByCode, $detailRow['product_code'] ?? null);
            if ($itemId === null) {
                $skippedCount++;
                continue;
            }

            $candidates = $poItemCandidates[$purchaseOrderId][$itemId] ?? [];
            $prsCode = $this->normalizeValue($detailRow['prs_code'] ?? null);

            $purchaseOrderItemId = $this->resolvePurchaseOrderItemId(
                $candidates,
                $prsCode,
                $usageByPoItemId,
            );

            if ($purchaseOrderItemId === null) {
                $missingPoItemCount++;
                continue;
            }

            $qtyGood = $this->normalizeDecimal($detailRow['qty_g'] ?? 0);
            $qtyBad = $this->normalizeDecimal($detailRow['qty_b'] ?? 0);
            if (($qtyGood + $qtyBad) <= 0) {
                $skippedCount++;
                continue;
            }

            $usageByPoItemId[$purchaseOrderItemId] = ($usageByPoItemId[$purchaseOrderItemId] ?? 0) + $qtyGood + $qtyBad;

            $detailCreatedAt = $this->parseDate($detailRow['created_date'] ?? null) ?? $fallbackCreatedAt;
            $detailUpdatedAt = $this->parseDate($detailRow['updated_date'] ?? null) ?? $fallbackUpdatedAt;
            $legacyDetailId = $this->normalizeInteger($detailRow['id'] ?? null);

            if (! isset($grouped[$purchaseOrderItemId])) {
                $grouped[$purchaseOrderItemId] = [
                    'purchase_order_item_id' => $purchaseOrderItemId,
                    'qty_good' => 0.0,
                    'qty_bad' => 0.0,
                    'created_at' => $detailCreatedAt,
                    'updated_at' => $detailUpdatedAt,
                    'meta' => [
                        'legacy_detail_ids' => [],
                        'prs_codes' => [],
                        'department_codes' => [],
                        'uom' => [],
                        'unit_cost_total' => 0.0,
                        'amount_total' => 0.0,
                    ],
                ];
            }

            $grouped[$purchaseOrderItemId]['qty_good'] += $qtyGood;
            $grouped[$purchaseOrderItemId]['qty_bad'] += $qtyBad;
            $grouped[$purchaseOrderItemId]['created_at'] = $this->earlierOf(
                $grouped[$purchaseOrderItemId]['created_at'],
                $detailCreatedAt,
            );
            $grouped[$purchaseOrderItemId]['updated_at'] = $this->laterOf(
                $grouped[$purchaseOrderItemId]['updated_at'],
                $detailUpdatedAt,
            );

            if ($legacyDetailId !== null) {
                $grouped[$purchaseOrderItemId]['meta']['legacy_detail_ids'][] = $legacyDetailId;
            }

            $departmentCode = $this->normalizeValue($detailRow['department_code'] ?? null);
            if ($departmentCode !== null) {
                $grouped[$purchaseOrderItemId]['meta']['department_codes'][] = $departmentCode;
            }

            if ($prsCode !== null) {
                $grouped[$purchaseOrderItemId]['meta']['prs_codes'][] = $prsCode;
            }

            $uom = $this->normalizeValue($detailRow['uom'] ?? null);
            if ($uom !== null) {
                $grouped[$purchaseOrderItemId]['meta']['uom'][] = $uom;
            }

            $grouped[$purchaseOrderItemId]['meta']['unit_cost_total'] += $this->normalizeDecimal($detailRow['unit_cost'] ?? 0);
            $grouped[$purchaseOrderItemId]['meta']['amount_total'] += $this->normalizeDecimal($detailRow['amount'] ?? 0);
        }

        $payloads = [];

        foreach ($grouped as $poItemId => $payload) {
            $payload['qty_good'] = round((float) $payload['qty_good'], 2);
            $payload['qty_bad'] = round((float) $payload['qty_bad'], 2);
            $payload['meta']['legacy_detail_ids'] = array_values(array_unique($payload['meta']['legacy_detail_ids']));
            $payload['meta']['prs_codes'] = array_values(array_unique($payload['meta']['prs_codes']));
            $payload['meta']['department_codes'] = array_values(array_unique($payload['meta']['department_codes']));
            $payload['meta']['uom'] = array_values(array_unique($payload['meta']['uom']));
            $payload['meta']['unit_cost_total'] = round((float) $payload['meta']['unit_cost_total'], 2);
            $payload['meta']['amount_total'] = round((float) $payload['meta']['amount_total'], 2);

            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, float>  $usageByPoItemId
     */
    private function resolvePurchaseOrderItemId(array $candidates, ?string $prsCode, array &$usageByPoItemId): ?int
    {
        if (empty($candidates)) {
            return null;
        }

        $candidatePool = $candidates;

        if ($prsCode !== null) {
            $normalizedPrs = $this->normalizeLookupText($prsCode);
            $prsMatched = array_values(array_filter($candidates, function (array $candidate) use ($normalizedPrs): bool {
                $candidatePrs = $candidate['prs_number'] ?? null;
                if (! is_string($candidatePrs)) {
                    return false;
                }

                return $this->normalizeLookupText($candidatePrs) === $normalizedPrs;
            }));

            if (! empty($prsMatched)) {
                $candidatePool = $prsMatched;
            }
        }

        $bestCandidateId = null;
        $bestRemainingQty = -INF;

        foreach ($candidatePool as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }

            $orderedQty = (float) ($candidate['quantity'] ?? 0);
            $usedQty = (float) ($usageByPoItemId[$candidateId] ?? 0);
            $remainingQty = $orderedQty - $usedQty;

            if ($remainingQty > $bestRemainingQty) {
                $bestRemainingQty = $remainingQty;
                $bestCandidateId = $candidateId;
            }
        }

        if ($bestCandidateId !== null) {
            return $bestCandidateId;
        }

        $firstCandidate = $candidatePool[0] ?? null;
        if (! is_array($firstCandidate)) {
            return null;
        }

        $fallbackId = (int) ($firstCandidate['id'] ?? 0);

        return $fallbackId > 0 ? $fallbackId : null;
    }

    /**
     * @return array<int, array<int, array<int, array<string, mixed>>>>
     */
    private function buildPurchaseOrderItemCandidates(): array
    {
        $rows = DB::table('purchase_order_items as poi')
            ->leftJoin('prs_items as pri', 'pri.id', '=', 'poi.prs_item_id')
            ->leftJoin('prs', 'prs.id', '=', 'pri.prs_id')
            ->select([
                'poi.id',
                'poi.purchase_order_id',
                'poi.item_id',
                'poi.quantity',
                'prs.prs_number',
            ])
            ->orderBy('poi.id')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $purchaseOrderId = (int) $row->purchase_order_id;
            $itemId = (int) $row->item_id;

            if ($purchaseOrderId <= 0 || $itemId <= 0) {
                continue;
            }

            $grouped[$purchaseOrderId][$itemId][] = [
                'id' => (int) $row->id,
                'quantity' => (float) $row->quantity,
                'prs_number' => $this->normalizeValue($row->prs_number ?? null),
            ];
        }

        return $grouped;
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

    private function resolveDefaultCustomsDocumentTypeId(): ?int
    {
        $byCode = DB::table('customs_document_types')
            ->where('code', 'BC 2.7')
            ->value('id');

        if ($byCode !== null) {
            return (int) $byCode;
        }

        $byName = DB::table('customs_document_types')
            ->where('name', 'like', '%Pemasukan%')
            ->orderBy('id')
            ->value('id');

        if ($byName !== null) {
            return (int) $byName;
        }

        $first = DB::table('customs_document_types')->orderBy('id')->value('id');

        return $first !== null ? (int) $first : null;
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

    private function earlierOf(Carbon $first, Carbon $second): Carbon
    {
        return $first->lessThanOrEqualTo($second) ? $first : $second;
    }

    private function laterOf(Carbon $first, Carbon $second): Carbon
    {
        return $first->greaterThanOrEqualTo($second) ? $first : $second;
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
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
        Log::warning("[ReceivingReportSeeder] {$message}");
    }
}
