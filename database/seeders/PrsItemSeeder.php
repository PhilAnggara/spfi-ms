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
        $defaultCanvaserId = $this->resolveLegacyFallbackUserId(2);

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

        $poDetailRows = $this->resolveRows('po_detail', fn (string $message) => $this->command?->warn($message));
        $this->command?->info("ℹ [po_detail] rows loaded: " . count($poDetailRows));

        $canvaserLookup = $this->buildPoDetailCanvaserLookup($poDetailRows);

        $inserted = 0;
        $skipped = 0;
        $canvaserFromPoDetail = 0;
        $canvaserFallback = 0;

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

            $legacyCanvaser = $this->consumePoDetailCanvaser(
                $canvaserLookup,
                $prsNumber,
                $productCode,
                $departmentCode
            );

            $canvaserId = $this->resolveLegacyUserId($legacyCanvaser, $defaultCanvaserId) ?? $defaultCanvaserId;

            if ($legacyCanvaser === null) {
                $canvaserFallback++;
            } else {
                $canvaserFromPoDetail++;
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
                    'canvaser_id' => $canvaserId,
                    'quantity' => $quantity,
                    'purchase_order_id' => null,
                    'selected_canvasing_item_id' => null,
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
            "✓ [prs_detail] Inserted/Updated: {$inserted}, Skipped: {$skipped}, Canvasser from po_detail: {$canvaserFromPoDetail}, Fallback: {$canvaserFallback} (user_id={$defaultCanvaserId})"
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
     * @param  array<int, array<string, mixed>>  $poDetailRows
     * @return array{strict: array<string, array<int, int>>, loose: array<string, array<int, int>>, values: array<int, string|null>, used: array<int, bool>}
     */
    protected function buildPoDetailCanvaserLookup(array $poDetailRows): array
    {
        $lookup = [
            'strict' => [],
            'loose' => [],
            'values' => [],
            'used' => [],
        ];

        foreach ($poDetailRows as $index => $row) {
            if ($this->isInactiveFlag($row['is_active'] ?? 'Y')) {
                continue;
            }

            $prsNumber = $this->normalizeLookupToken($row['prsnumber'] ?? null);
            $productCode = $this->normalizeLookupToken($row['product_code'] ?? ($row['productcode'] ?? null));

            if ($prsNumber === null || $productCode === null) {
                continue;
            }

            $departmentCode = $this->normalizeLookupToken($row['department_code'] ?? null);
            $strictKey = $this->buildPrsItemLookupKey($prsNumber, $productCode, $departmentCode);
            $looseKey = $this->buildPrsItemLookupKey($prsNumber, $productCode, null);

            $candidateId = (int) $index;

            $lookup['strict'][$strictKey][] = $candidateId;
            $lookup['loose'][$looseKey][] = $candidateId;
            $lookup['values'][$candidateId] = $this->normalizeLookupToken($row['created_by'] ?? null);
            $lookup['used'][$candidateId] = false;
        }

        return $lookup;
    }

    /**
     * @param  array{strict: array<string, array<int, int>>, loose: array<string, array<int, int>>, values: array<int, string|null>, used: array<int, bool>}  $lookup
     */
    protected function consumePoDetailCanvaser(array &$lookup, string $prsNumber, string $productCode, ?string $departmentCode): ?string
    {
        $prsNumberKey = $this->normalizeLookupToken($prsNumber);
        $productCodeKey = $this->normalizeLookupToken($productCode);

        if ($prsNumberKey === null || $productCodeKey === null) {
            return null;
        }

        $strictKey = $this->buildPrsItemLookupKey($prsNumberKey, $productCodeKey, $departmentCode);
        $looseKey = $this->buildPrsItemLookupKey($prsNumberKey, $productCodeKey, null);

        $strictCandidate = $this->consumeLookupCandidate($lookup, 'strict', $strictKey);
        if ($strictCandidate !== null) {
            return $strictCandidate;
        }

        return $this->consumeLookupCandidate($lookup, 'loose', $looseKey);
    }

    /**
     * @param  array{strict: array<string, array<int, int>>, loose: array<string, array<int, int>>, values: array<int, string|null>, used: array<int, bool>}  $lookup
     */
    protected function consumeLookupCandidate(array &$lookup, string $bucket, string $key): ?string
    {
        if (! isset($lookup[$bucket][$key])) {
            return null;
        }

        while (! empty($lookup[$bucket][$key])) {
            $candidateId = array_shift($lookup[$bucket][$key]);

            if ($candidateId === null) {
                continue;
            }

            if (($lookup['used'][$candidateId] ?? true) === true) {
                continue;
            }

            $lookup['used'][$candidateId] = true;

            return $lookup['values'][$candidateId] ?? null;
        }

        return null;
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

    protected function isInactiveFlag(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['N', '0', 'FALSE', 'F', 'NO'], true);
    }
}
