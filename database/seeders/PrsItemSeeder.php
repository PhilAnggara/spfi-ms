<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrsItemSeeder extends Seeder
{
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->isLegacySource()) {
            $this->seedFromLegacy();
            return;
        }

        // Fallback ke local seeder (data manual yang sudah ada).
        $this->seedLocal();
    }

    /**
     * Seed PRS Items dari legacy database (prs_detail).
     */
    protected function seedFromLegacy(): void
    {
        $this->logImportSource('prs_detail', 'legacy');

        // Build lookup maps
        $prsIdByNumber = DB::table('prs')->pluck('id', 'prs_number')->all();
        $itemIdByCode = DB::table('items')->pluck('id', 'code')->all();

        $this->prepareLegacyUserLookup();
        $defaultCanvasserId = $this->resolveLegacyFallbackUserId(2);

        if (empty($prsIdByNumber)) {
            $this->warn("No PRS records found in new DB. Make sure PrsSeeder ran first.");
            return;
        }

        // Ambil semua baris legacy sekaligus (chunk tidak kompatibel dgn SQL Server lama).
        $legacyRows = $this->resolveRows('prs_detail', fn (string $message) => $this->command?->warn($message));

        if (empty($legacyRows)) {
            $this->warn("No legacy prs_detail rows loaded.");
            return;
        }

        $this->command?->info("ℹ [prs_detail] rows loaded: " . count($legacyRows));

        $assignCanvRows = $this->resolveRows('assign_canv', fn (string $message) => $this->command?->warn($message));
        $assignCanvDtlRows = $this->resolveRows('assign_canv_dtl', fn (string $message) => $this->command?->warn($message));

        $this->command?->info("ℹ [assign_canv] rows loaded: " . count($assignCanvRows));
        $this->command?->info("ℹ [assign_canv_dtl] rows loaded: " . count($assignCanvDtlRows));

        $assignmentLookup = $this->buildAssignCanvLookup($assignCanvRows, $assignCanvDtlRows);

        $inserted = 0;
        $skipped = 0;
        $assignmentMatched = 0;
        $assignmentMissingTimestamp = 0;
        $canvasserFallback = 0;

        foreach ($legacyRows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));
            $productCode = trim((string) ($data['productcode'] ?? ''));
            $departmentCode = $this->normalizeLookupToken($data['department_code'] ?? null);

            // Lookup prs_id
            $prsId = $prsIdByNumber[$prsNumber] ?? null;
            if ($prsId === null) {
                $this->warn("PRS Item skipped: prsnumber '{$prsNumber}' not found in prs table");
                $skipped++;
                continue;
            }

            // Lookup item_id
            $itemId = $itemIdByCode[$productCode] ?? null;
            if ($itemId === null) {
                $this->warn("PRS Item skipped: productcode '{$productCode}' not found in items table (prsnumber: {$prsNumber})");
                $skipped++;
                continue;
            }

            $createdDate = $this->parseDate($data['created_date'] ?? null);
            $updatedDate = $this->parseDate($data['updated_date'] ?? null);

            $isActive = strtoupper(trim((string) ($data['is_active'] ?? 'Y'))) === 'Y';
            $deletedAt = $isActive ? null : $updatedDate;

            $quantity = (int) ($data['qty'] ?? 0);

            $assignment = $this->resolveAssignCanvForPrsItem(
                $assignmentLookup,
                $prsNumber,
                $productCode,
                $departmentCode
            );

            $legacyCanvasser = $assignment['canvasser'] ?? null;
            $assignedCanvasserAt = $assignment['assigned_at'] ?? null;

            if ($assignment !== null) {
                $assignmentMatched++;

                if ($assignedCanvasserAt === null) {
                    $assignmentMissingTimestamp++;
                }
            }

            $canvasserId = $this->resolveLegacyUserId($legacyCanvasser, $defaultCanvasserId) ?? $defaultCanvasserId;

            if ($legacyCanvasser === null) {
                $canvasserFallback++;
            }

            // Upsert berdasarkan prs_id + item_id agar idempotent.
            DB::table('prs_items')->updateOrInsert(
                [
                    'prs_id' => $prsId,
                    'item_id' => $itemId,
                ],
                [
                    'prs_id' => $prsId,
                    'item_id' => $itemId,
                    'canvasser_id' => $canvasserId,
                    'assigned_canvasser_at' => $assignedCanvasserAt?->toDateTimeString(),
                    'quantity' => $quantity,
                    'purchase_order_id' => null,
                    'selected_canvassing_item_id' => null,
                    'selection_reason' => null,
                    'is_direct_purchase' => false,
                    'created_at' => $createdDate ?? now(),
                    'updated_at' => $updatedDate ?? now(),
                    'deleted_at' => $deletedAt,
                ]
            );

            $inserted++;
        }

        $this->command?->info(
            "✓ [prs_detail] Inserted/Updated: {$inserted}, Skipped: {$skipped}, Assignment matched: {$assignmentMatched}, Missing assign timestamp: {$assignmentMissingTimestamp}, Canvasser fallback: {$canvasserFallback} (user_id={$defaultCanvasserId})"
        );
    }

    /**
     * Seed PRS Items dari local seeder (data manual yang sudah ada sebelumnya).
     */
    protected function seedLocal(): void
    {
        $this->logImportSource('prs_detail', 'local');

        DB::table('prs_items')->insert([
            [
                'prs_id' => 1,
                'item_id' => 1,
                'quantity' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 1,
                'item_id' => 2,
                'quantity' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 2,
                'item_id' => 3,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 2,
                'item_id' => 4,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 3,
                'item_id' => 1,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    // ─── helpers ────────────────────────────────────────────────────────

    protected function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '' || strtoupper(trim((string) $value)) === 'NULL') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    protected function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[PrsItemSeeder] {$message}");
    }

    /**
     * @param  array<int, array<string, mixed>>  $assignCanvRows
     * @param  array<int, array<string, mixed>>  $assignCanvDtlRows
     * @return array{strict: array<string, array{canvasser: string|null, assigned_at: Carbon|null}>, loose: array<string, array{canvasser: string|null, assigned_at: Carbon|null}>}
     */
    protected function buildAssignCanvLookup(array $assignCanvRows, array $assignCanvDtlRows): array
    {
        $lookup = [
            'strict' => [],
            'loose' => [],
        ];

        $assignTimestampByHeaderId = [];

        foreach ($assignCanvRows as $row) {
            if ($this->isInactiveFlag($row['is_active'] ?? 'Y')) {
                continue;
            }

            $headerId = $this->resolveAssignCanvHeaderId($row);
            if ($headerId === null) {
                continue;
            }

            $assignedAt = $this->resolveAssignedCanvasserAt($row);

            if (!isset($assignTimestampByHeaderId[$headerId]) || $this->shouldReplaceAssignment($assignTimestampByHeaderId[$headerId], $assignedAt, null, null)) {
                $assignTimestampByHeaderId[$headerId] = $assignedAt;
            }
        }

        foreach ($assignCanvDtlRows as $row) {
            if ($this->isInactiveFlag($row['is_active'] ?? 'Y')) {
                continue;
            }

            $prsNumber = $this->normalizeLookupToken($row['prsnumber'] ?? ($row['prs_number'] ?? null));
            $productCode = $this->normalizeLookupToken($row['product_code'] ?? ($row['productcode'] ?? null));

            if ($prsNumber === null || $productCode === null) {
                continue;
            }

            $departmentCode = $this->normalizeLookupToken($row['department_code'] ?? ($row['departmentcode'] ?? null));
            $strictKey = $this->buildPrsItemLookupKey($prsNumber, $productCode, $departmentCode);
            $looseKey = $this->buildPrsItemLookupKey($prsNumber, $productCode, null);

            $legacyCanvasser = $this->normalizeLookupToken(
                $row['created_by']
                ?? ($row['canvasser']
                ?? ($row['canvasser_id']
                ?? ($row['assigned_to']
                ?? ($row['assign_to'] ?? null))))
            );

            $headerRefId = $this->resolveAssignCanvHeaderRefId($row);
            $assignedAt = $headerRefId !== null ? ($assignTimestampByHeaderId[$headerRefId] ?? null) : null;

            if ($assignedAt === null) {
                $assignedAt = $this->resolveAssignedCanvasserAt($row);
            }

            $this->upsertBestAssignment($lookup['strict'], $strictKey, $legacyCanvasser, $assignedAt);
            $this->upsertBestAssignment($lookup['loose'], $looseKey, $legacyCanvasser, $assignedAt);
        }

        return $lookup;
    }

    /**
     * @param  array{strict: array<string, array{canvasser: string|null, assigned_at: Carbon|null}>, loose: array<string, array{canvasser: string|null, assigned_at: Carbon|null}>}  $lookup
     */
    protected function resolveAssignCanvForPrsItem(array $lookup, string $prsNumber, string $productCode, ?string $departmentCode): ?array
    {
        $prsNumberKey = $this->normalizeLookupToken($prsNumber);
        $productCodeKey = $this->normalizeLookupToken($productCode);

        if ($prsNumberKey === null || $productCodeKey === null) {
            return null;
        }

        $strictKey = $this->buildPrsItemLookupKey($prsNumberKey, $productCodeKey, $departmentCode);
        $looseKey = $this->buildPrsItemLookupKey($prsNumberKey, $productCodeKey, null);

        if (isset($lookup['strict'][$strictKey])) {
            return $lookup['strict'][$strictKey];
        }

        return $lookup['loose'][$looseKey] ?? null;
    }

    /**
     * @param  array<string, array{canvasser: string|null, assigned_at: Carbon|null}>  $bucket
     */
    protected function upsertBestAssignment(array &$bucket, string $key, ?string $legacyCanvasser, ?Carbon $assignedAt): void
    {
        if (!isset($bucket[$key])) {
            $bucket[$key] = [
                'canvasser' => $legacyCanvasser,
                'assigned_at' => $assignedAt,
            ];
            return;
        }

        $current = $bucket[$key];

        if ($this->shouldReplaceAssignment($current['assigned_at'] ?? null, $assignedAt, $current['canvasser'] ?? null, $legacyCanvasser)) {
            $bucket[$key] = [
                'canvasser' => $legacyCanvasser,
                'assigned_at' => $assignedAt,
            ];
        }
    }

    protected function shouldReplaceAssignment(?Carbon $currentAssignedAt, ?Carbon $candidateAssignedAt, ?string $currentCanvasser, ?string $candidateCanvasser): bool
    {
        if ($currentAssignedAt === null && $candidateAssignedAt !== null) {
            return true;
        }

        if ($currentAssignedAt !== null && $candidateAssignedAt !== null) {
            return $candidateAssignedAt->greaterThanOrEqualTo($currentAssignedAt);
        }

        if ($currentAssignedAt === null && $candidateAssignedAt === null) {
            return $currentCanvasser === null && $candidateCanvasser !== null;
        }

        return false;
    }

    protected function resolveAssignCanvHeaderId(array $row): ?string
    {
        return $this->normalizeLookupToken(
            $row['id']
            ?? ($row['assign_canv_id']
            ?? ($row['assign_id'] ?? null))
        );
    }

    protected function resolveAssignCanvHeaderRefId(array $row): ?string
    {
        return $this->normalizeLookupToken(
            $row['assign_canv_id']
            ?? ($row['assigncanv_id']
            ?? ($row['assign_id']
            ?? ($row['header_id'] ?? null)))
        );
    }

    protected function resolveAssignedCanvasserAt(array $row): ?Carbon
    {
        return $this->parseDate(
            $row['created_date']
            ?? ($row['assign_date']
            ?? ($row['assigned_date']
            ?? ($row['updated_date'] ?? null)))
        );
    }

    protected function isInactiveFlag(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['N', '0', 'FALSE', 'F', 'NO'], true);
    }

    protected function buildPrsItemLookupKey(string $prsNumber, string $productCode, ?string $departmentCode): string
    {
        $departmentKey = $departmentCode ?? '*';

        return $prsNumber . '|' . $productCode . '|' . $departmentKey;
    }

    protected function normalizeLookupToken(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtoupper($normalized) === 'NULL') {
            return null;
        }

        return strtolower(trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized));
    }

}
