<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderSeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    private const DEFAULT_STATUS = 'DRAFT';

    /**
     * @var array<int, array{name: string, role: string|null}>
     */
    private array $userProfileById = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $poRows = $this->loadRows('po');
        $poDetailRows = $this->loadRows('po_detail');

        if (empty($poRows)) {
            $this->warn('No PO rows found from configured source.');
            return;
        }

        $this->prepareLegacyUserLookup(['role']);
        $this->userProfileById = [];

        foreach ($this->getLegacyUserLookupRowsById() as $id => $user) {
            $this->userProfileById[(int) $id] = [
                'name' => $this->normalizeValue($user['name'] ?? null) ?? 'Unknown',
                'role' => $this->normalizeValue($user['role'] ?? null),
            ];
        }

        $defaultUserId = $this->resolveLegacyFallbackUserId(2);
        $defaultCurrencyId = (int) (DB::table('currencies')->orderBy('id')->value('id') ?? 1);

        $supplierIdByCode = $this->buildCodeLookup(DB::table('suppliers')->pluck('id', 'code')->all());
        $currencyIdByCode = $this->buildCodeLookup(DB::table('currencies')->pluck('id', 'code')->all());
        $currencyIdByName = $this->buildCodeLookup(DB::table('currencies')->pluck('id', 'name')->all());
        $itemIdByCode = $this->buildItemLookup();

        $prsCandidates = $this->buildPrsItemCandidates();

        $detailsByPoCode = [];
        foreach ($poDetailRows as $row) {
            $poCode = $this->normalizeValue($row['po_code'] ?? null);
            if ($poCode === null) {
                continue;
            }

            $detailsByPoCode[$poCode][] = $row;
        }

        $insertedPo = 0;
        $skippedPo = 0;
        $insertedDetail = 0;
        $skippedDetail = 0;

        foreach ($poRows as $poRow) {
            $poCode = $this->normalizeValue($poRow['po_code'] ?? null);
            if ($poCode === null) {
                $skippedPo++;
                continue;
            }

            $supplierId = $this->resolveByCode($supplierIdByCode, $poRow['supplier_code'] ?? null);
            if ($supplierId === null) {
                $this->warn("PO skipped: supplier_code not found for po_code {$poCode}");
                $skippedPo++;
                continue;
            }

            $currencyId = $this->resolveByCode($currencyIdByCode, $poRow['currency'] ?? null)
                ?? $this->resolveByCode($currencyIdByName, $poRow['currency'] ?? null)
                ?? $defaultCurrencyId;

            $createdById = $this->resolveLegacyUserId($poRow['created_by'] ?? null, $defaultUserId) ?? $defaultUserId;
            $certifiedById = $this->resolveLegacyUserId($poRow['certified_by'] ?? null, $defaultUserId, true);
            $approvedById = $this->resolveLegacyUserId($poRow['approved_by'] ?? null, $defaultUserId, true);

            $discountRate = $this->normalizeRate($poRow['discount'] ?? 0);
            $ppnRate = $this->normalizeRate($poRow['ppn'] ?? 0);
            $pphRate = $this->normalizeRate($poRow['pph'] ?? 0);

            $poDate = $this->parseDate($poRow['po_date'] ?? null);
            $createdAt = $this->parseDate($poRow['created_date'] ?? null) ?? $poDate ?? now();
            $updatedAt = $this->parseDate($poRow['updated_date'] ?? null) ?? $createdAt;
            $approvedAt = $this->parseDate($poRow['approved_date'] ?? null);
            $certifiedAt = $this->parseDate($poRow['certified_date'] ?? null);
            $submittedAt = $poDate ?? $createdAt;

            $isApproved = $this->isAffirmative($poRow['is_approved'] ?? null);
            $isCertified = $this->isAffirmative($poRow['is_certified'] ?? null);
            $isActive = ! $this->isNegative($poRow['is_active'] ?? 'Y');

            $status = $this->mapPoStatus($isApproved, $isCertified);
            $remarks = $this->normalizeValue($poRow['remarks'] ?? null);
            $remarkType = $this->detectRemarkType($remarks);

            $detailRows = $detailsByPoCode[$poCode] ?? [];

            $feeAmount = 0.0;
            $lineSubtotalTotal = 0.0;
            $discountAmountTotal = 0.0;
            $ppnAmountTotal = 0.0;
            $pphAmountTotal = 0.0;

            $detailPayloads = [];

            foreach ($detailRows as $detailRow) {
                if ($this->isNegative($detailRow['is_active'] ?? 'Y')) {
                    continue;
                }

                $detailId = $this->normalizeInteger($detailRow['id'] ?? null);
                $itemId = $this->resolveByCode($itemIdByCode, $detailRow['product_code'] ?? null);

                if ($itemId === null) {
                    $skippedDetail++;
                    $this->warn("PO detail skipped: product_code not found for po_code {$poCode}");
                    continue;
                }

                $prsNumber = $this->normalizeValue($detailRow['prsnumber'] ?? null);
                $departmentCode = $this->normalizeValue($detailRow['department_code'] ?? null);
                $prsCandidate = $this->consumePrsItemCandidate($prsCandidates, $prsNumber, $itemId, $departmentCode);

                $lineSubtotal = $this->normalizeDecimal($detailRow['sub_total'] ?? 0);
                if ($lineSubtotal < 0) {
                    $lineSubtotal = 0;
                }

                $quantity = $prsCandidate['quantity'] ?? 0.0;
                if ($quantity <= 0) {
                    $quantity = 1.0;
                }

                $unitPrice = $quantity > 0 ? ($lineSubtotal / $quantity) : $lineSubtotal;
                $discountAmount = $lineSubtotal * ($discountRate / 100);
                $baseAmount = $lineSubtotal - $discountAmount;
                $ppnAmount = $baseAmount * ($ppnRate / 100);
                $pphAmount = $baseAmount * ($pphRate / 100);
                $lineTotal = $baseAmount + $ppnAmount - $pphAmount;

                $lineSubtotalTotal += $lineSubtotal;
                $discountAmountTotal += $discountAmount;
                $ppnAmountTotal += $ppnAmount;
                $pphAmountTotal += $pphAmount;

                $detailCreatedAt = $this->parseDate($detailRow['created_date'] ?? null) ?? $createdAt;
                $detailUpdatedAt = $this->parseDate($detailRow['updated_date'] ?? null) ?? $updatedAt;

                $detailPayloads[] = [
                    'legacy_id' => $detailId,
                    'prs_item_id' => $prsCandidate['id'] ?? null,
                    'item_id' => $itemId,
                    'quantity' => round($quantity, 2),
                    'unit_price' => round($unitPrice, 2),
                    'line_subtotal' => round($lineSubtotal, 2),
                    'discount_rate' => $discountRate,
                    'discount_amount' => round($discountAmount, 2),
                    'ppn_rate' => $ppnRate,
                    'ppn_amount' => round($ppnAmount, 2),
                    'pph_rate' => $pphRate,
                    'pph_amount' => round($pphAmount, 2),
                    'total' => round($lineTotal, 2),
                    'notes' => null,
                    'meta' => [
                        'prs_number' => $prsNumber,
                        'department_code' => $departmentCode,
                        'legacy_po_code' => $poCode,
                        'legacy_detail_id' => $detailId,
                        'legacy_detail_created_by' => $this->normalizeValue($detailRow['created_by'] ?? null),
                    ],
                    'created_at' => $detailCreatedAt,
                    'updated_at' => $detailUpdatedAt,
                ];
            }

            $total = $lineSubtotalTotal - $discountAmountTotal + $ppnAmountTotal - $pphAmountTotal + $feeAmount;

            $signatureMeta = [
                'legacy' => [
                    'rate_id' => $this->normalizeValue($poRow['rate_id'] ?? null),
                    'is_bc' => $this->normalizeValue($poRow['is_BC'] ?? null),
                    'certified_date' => $certifiedAt?->toDateTimeString(),
                ],
            ];

            if ($certifiedById !== null) {
                $signatureMeta['certified_by'] = $this->buildSignatureUserMeta($certifiedById);
            }

            if ($approvedById !== null) {
                $signatureMeta['approved_by'] = $this->buildSignatureUserMeta($approvedById);
            }

            $persistPurchaseOrder = function () use (
                $poCode,
                $supplierId,
                $createdById,
                $status,
                $lineSubtotalTotal,
                $feeAmount,
                $total,
                $certifiedById,
                $approvedById,
                $submittedAt,
                $approvedAt,
                $remarks,
                $signatureMeta,
                $createdAt,
                $updatedAt,
                $isActive,
                $currencyId,
                $discountRate,
                $discountAmountTotal,
                $ppnRate,
                $ppnAmountTotal,
                $pphRate,
                $pphAmountTotal,
                $remarkType,
                $detailPayloads,
                &$insertedDetail,
                &$skippedDetail
            ): void {
                DB::table('purchase_orders')->updateOrInsert(
                    ['po_number' => $poCode],
                    [
                        'supplier_id' => $supplierId,
                        'created_by' => $createdById,
                        'status' => $status,
                        'subtotal' => round($lineSubtotalTotal, 2),
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'fees' => round($feeAmount, 2),
                        'total' => round($total, 2),
                        'certified_by_user_id' => $certifiedById,
                        'approved_by_user_id' => $approvedById,
                        'submitted_at' => $submittedAt,
                        'approved_at' => $approvedAt,
                        'approval_notes' => null,
                        'signature_meta' => json_encode($signatureMeta),
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'deleted_at' => $isActive ? null : $updatedAt,
                        'currency_id' => $currencyId,
                        'discount_rate' => $discountRate,
                        'discount_amount' => round($discountAmountTotal, 2),
                        'ppn_rate' => $ppnRate,
                        'ppn_amount' => round($ppnAmountTotal, 2),
                        'pph_rate' => $pphRate,
                        'pph_amount' => round($pphAmountTotal, 2),
                        'remark_type' => $remarkType,
                        'remark_text' => $remarks,
                    ]
                );

                $purchaseOrderId = DB::table('purchase_orders')
                    ->where('po_number', $poCode)
                    ->value('id');

                if (! $purchaseOrderId) {
                    return;
                }

                foreach ($detailPayloads as $payload) {
                    $legacyId = $payload['legacy_id'];
                    unset($payload['legacy_id']);

                    $payload['purchase_order_id'] = (int) $purchaseOrderId;
                    $payload['meta'] = json_encode($payload['meta']);

                    if ($legacyId !== null && ! $this->isSqlServer()) {
                        DB::table('purchase_order_items')->updateOrInsert(
                            ['id' => $legacyId],
                            ['id' => $legacyId] + $payload
                        );
                    } else {
                        DB::table('purchase_order_items')->insert($payload);
                    }

                    $insertedDetail++;

                    if (! empty($payload['prs_item_id'])) {
                        DB::table('prs_items')
                            ->where('id', $payload['prs_item_id'])
                            ->update([
                                'purchase_order_id' => (int) $purchaseOrderId,
                                'updated_at' => $payload['updated_at'],
                            ]);
                    } else {
                        $skippedDetail++;
                    }
                }
            };

            if ($this->isSqlServer()) {
                $this->runWithSqlServerReconnect($persistPurchaseOrder, "po_code {$poCode}");
            } else {
                DB::transaction($persistPurchaseOrder);
            }

            $insertedPo++;
        }

        $this->command?->info("✓ [po] Inserted/Updated: {$insertedPo}, Skipped: {$skippedPo}");
        $this->command?->info("✓ [po_detail] Inserted/Updated: {$insertedDetail}, Missing PRS link: {$skippedDetail}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array{strict: array<string, array<int, array{id: int, quantity: float}>>, loose: array<string, array<int, array{id: int, quantity: float}>>}
     */
    private function buildPrsItemCandidates(): array
    {
        $rows = DB::table('prs_items')
            ->join('prs', 'prs.id', '=', 'prs_items.prs_id')
            ->leftJoin('departments', 'departments.id', '=', 'prs.department_id')
            ->select([
                'prs_items.id as id',
                'prs_items.item_id as item_id',
                'prs_items.quantity as quantity',
                'prs.prs_number as prs_number',
                'departments.code as department_code',
            ])
            ->orderBy('prs_items.id')
            ->get();

        $strict = [];
        $loose = [];

        foreach ($rows as $row) {
            $prsNumber = $this->normalizeValue($row->prs_number ?? null);
            if ($prsNumber === null) {
                continue;
            }

            $itemId = (int) $row->item_id;
            $departmentCode = $this->normalizeValue($row->department_code ?? null);
            $candidate = [
                'id' => (int) $row->id,
                'quantity' => (float) $row->quantity,
            ];

            $strictKey = $this->buildPrsKey($prsNumber, $itemId, $departmentCode);
            $looseKey = $this->buildPrsKey($prsNumber, $itemId, null);

            $strict[$strictKey][] = $candidate;
            $loose[$looseKey][] = $candidate;
        }

        return [
            'strict' => $strict,
            'loose' => $loose,
        ];
    }

    /**
     * @param  array{strict: array<string, array<int, array{id: int, quantity: float}>>, loose: array<string, array<int, array{id: int, quantity: float}>>}  $prsCandidates
     * @return array{id: int, quantity: float}|null
     */
    private function consumePrsItemCandidate(array &$prsCandidates, ?string $prsNumber, int $itemId, ?string $departmentCode): ?array
    {
        if ($prsNumber === null || $itemId <= 0) {
            return null;
        }

        $strictKey = $this->buildPrsKey($prsNumber, $itemId, $departmentCode);
        $looseKey = $this->buildPrsKey($prsNumber, $itemId, null);

        $picked = null;

        if (! empty($prsCandidates['strict'][$strictKey])) {
            $picked = array_shift($prsCandidates['strict'][$strictKey]);
        } elseif (! empty($prsCandidates['loose'][$looseKey])) {
            $picked = array_shift($prsCandidates['loose'][$looseKey]);
        }

        if ($picked !== null) {
            $this->removeCandidateById($prsCandidates['strict'], $picked['id']);
            $this->removeCandidateById($prsCandidates['loose'], $picked['id']);
        }

        return $picked;
    }

    /**
     * @param  array<string, array<int, array{id: int, quantity: float}>>  $queue
     */
    private function removeCandidateById(array &$queue, int $candidateId): void
    {
        foreach ($queue as $key => $items) {
            foreach ($items as $idx => $candidate) {
                if ((int) $candidate['id'] === $candidateId) {
                    unset($queue[$key][$idx]);
                    $queue[$key] = array_values($queue[$key]);
                    return;
                }
            }
        }
    }

    private function buildPrsKey(string $prsNumber, int $itemId, ?string $departmentCode): string
    {
        $dept = $departmentCode !== null ? $this->normalizeLookupText($departmentCode) : '';
        return $this->normalizeLookupText($prsNumber) . '|' . $itemId . '|' . $dept;
    }

    private function mapPoStatus(bool $isApproved, bool $isCertified): string
    {
        if ($isApproved) {
            return 'APPROVED';
        }

        if ($isCertified) {
            return 'PENDING_APPROVAL';
        }

        return self::DEFAULT_STATUS;
    }

    private function detectRemarkType(?string $remark): string
    {
        if ($remark === null) {
            return 'Normal';
        }

        return str_contains(strtolower($remark), 'confirm') ? 'Confirmatory' : 'Normal';
    }

    /**
     * @return array{user_id: int, name: string, title: string|null}
     */
    private function buildSignatureUserMeta(int $userId): array
    {
        $profile = $this->userProfileById[$userId] ?? ['name' => 'Unknown', 'role' => null];

        return [
            'user_id' => $userId,
            'name' => $profile['name'],
            'title' => $profile['role'],
        ];
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

    private function normalizeRate(mixed $value): float
    {
        return round($this->normalizeDecimal($value), 2);
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

            // Reject dates before 1970-01-01 as they're likely placeholder dates
            // MySQL may reject very old dates depending on configuration
            if ($date->year < 1970) {
                return null;
            }

            return $date;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isAffirmative(mixed $value): bool
    {
        $normalized = strtoupper((string) ($this->normalizeValue($value) ?? ''));
        return in_array($normalized, ['Y', 'YES', '1', 'TRUE', 'T'], true);
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

    private function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[PurchaseOrderSeeder] {$message}");
    }
}
