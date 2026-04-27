<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PrsItemSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legacyRows = $this->resolveRows('prs_detail', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $assignCanvRows = $this->resolveRows('assign_canv', fn (string $message) => $this->command?->warn($message));
            $assignCanvDtlRows = $this->resolveRows('assign_canv_dtl', fn (string $message) => $this->command?->warn($message));

            $this->seedRows($legacyRows, $assignCanvRows, $assignCanvDtlRows, 'legacy');
            return;
        }

        $prsDetailRows = $this->readCsvRows('prs_detail');

        if (empty($prsDetailRows)) {
            $this->warn('No CSV prs_detail rows loaded.');
            return;
        }

        $assignCanvRows = $this->readCsvRows('assign_canv');
        $assignCanvDtlRows = $this->readCsvRows('assign_canv_dtl');

        $this->seedRows($prsDetailRows, $assignCanvRows, $assignCanvDtlRows, 'csv');
    }

    /**
     * Seed PRS Items dari baris impor ter-normalisasi.
     */
    protected function seedRows(array $rows, array $assignCanvRows, array $assignCanvDtlRows, string $source): void
    {
        $this->logImportSource('prs_detail', $source);

        // Build lookup maps
        $prsIdByNumber = DB::table('prs')->pluck('id', 'prs_number')->all();
        $itemIdByCode = DB::table('items')->pluck('id', 'code')->all();

        $legacyCanvasserMap = $this->getLegacyCanvasserCodeToUsernameMap();
        $mappedCanvasserIdByLegacyCode = $this->resolveMappedCanvasserIds($legacyCanvasserMap);

        if (empty($prsIdByNumber)) {
            $this->warn("No PRS records found in new DB. Make sure PrsSeeder ran first.");
            return;
        }

        $this->command?->info("ℹ [prs_detail] rows loaded: " . count($rows));

        $this->command?->info("ℹ [assign_canv] rows loaded: " . count($assignCanvRows));
        $this->command?->info("ℹ [assign_canv_dtl] rows loaded: " . count($assignCanvDtlRows));

        $assignmentLookup = $this->buildAssignCanvLookup($assignCanvRows, $assignCanvDtlRows);

        $inserted = 0;
        $skipped = 0;
        $assignmentMatched = 0;
        $assignmentMissingTimestamp = 0;
        $assignmentMappedCanvasser = 0;
        $assignmentUnmappedCanvasser = 0;
        $assignmentMissingCanvasserCode = 0;
        $unmappedLegacyCanvasserCodes = [];

        foreach ($rows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));
            $productCode = trim((string) ($data['productcode'] ?? ''));
            $departmentCode = $this->normalizeLookupToken($data['department_code'] ?? null);

            if ($prsNumber === '' || $productCode === '') {
                $this->warn("PRS Item skipped: missing prsnumber or productcode");
                $skipped++;
                continue;
            }

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

            $legacyCanvasserCode = $assignment['canvasser'] ?? null;
            $assignedCanvasserAt = $assignment['assigned_at'] ?? null;

            if ($assignment !== null) {
                $assignmentMatched++;

                if ($assignedCanvasserAt === null) {
                    $assignmentMissingTimestamp++;
                }
            }

            $canvasserId = null;

            if ($legacyCanvasserCode === null) {
                $assignmentMissingCanvasserCode++;
            } else {
                $canvasserId = $mappedCanvasserIdByLegacyCode[$legacyCanvasserCode] ?? null;

                if ($canvasserId !== null) {
                    $assignmentMappedCanvasser++;
                } else {
                    $assignmentUnmappedCanvasser++;
                    $unmappedLegacyCanvasserCodes[$legacyCanvasserCode] = true;
                    $this->warn("Unmapped legacy canvasser code '{$legacyCanvasserCode}' for prsnumber '{$prsNumber}', productcode '{$productCode}', department '{$departmentCode}'");
                }
            }

            $attributes = [
                'prs_id' => $prsId,
                'item_id' => $itemId,
            ];

            $values = [
                'prs_id' => $prsId,
                'item_id' => $itemId,
                'canvasser_id' => $canvasserId,
                'assigned_canvasser_at' => $assignedCanvasserAt?->toDateTimeString(),
                'quantity' => $quantity,
                'purchase_order_id' => null,
                'selected_canvassing_item_id' => null,
                'selection_reason' => null,
                'is_direct_purchase' => false,
                'meta' => json_encode($data),
                'created_at' => $createdDate ?? now(),
                'updated_at' => $updatedDate ?? now(),
                'deleted_at' => $deletedAt,
            ];

            if (! $this->upsertPrsItemWithRetry($attributes, $values, $prsNumber, $productCode)) {
                $skipped++;
                continue;
            }

            $inserted++;
        }

        $unmappedCodes = implode(', ', array_keys($unmappedLegacyCanvasserCodes));
        $unmappedCodesText = $unmappedCodes !== '' ? $unmappedCodes : '-';

        $this->command?->info(
            "✓ [prs_detail] Inserted/Updated: {$inserted}, Skipped: {$skipped}, Assignment matched: {$assignmentMatched}, Missing assign timestamp: {$assignmentMissingTimestamp}, Canvasser mapped: {$assignmentMappedCanvasser}, Canvasser unmapped: {$assignmentUnmappedCanvasser}, Missing canvasser code: {$assignmentMissingCanvasserCode}, Unmapped legacy codes: {$unmappedCodesText}"
        );
    }

    /**
     * Read semicolon-delimited rows from configured CSV dataset.
     */
    protected function readCsvRows(string $dataset): array
    {
        $csvPath = $this->csvPathFor($dataset);

        if (!File::exists($csvPath)) {
            $this->warn("{$dataset}.csv not found at: {$csvPath}");
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->warn("Failed to open {$dataset}.csv at: {$csvPath}");
            return [];
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            $this->warn("{$dataset}.csv is empty: {$csvPath}");
            return [];
        }

        $rows = [];
        $skippedInvalidColumns = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                $skippedInvalidColumns++;
                continue;
            }

            $rows[] = $data;
        }

        fclose($handle);

        if ($skippedInvalidColumns > 0) {
            $this->warn("{$dataset}.csv skipped rows with invalid columns: {$skippedInvalidColumns}");
        }

        return $rows;
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

            // Gunakan hanya kode user assignment dari legacy untuk hindari mismatch karena heuristik nama.
            $legacyCanvasser = $this->normalizeLegacyCanvasserCode(
                $row['user_id']
                ?? ($row['userid']
                ?? ($row['user_code'] ?? null))
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

    /**
     * @return array<string, string>
     */
    protected function getLegacyCanvasserCodeToUsernameMap(): array
    {
        return [
            'USER0005' => 'sta.prc',
            'USER0007' => 'jeffry.lantang',
            'USER0008' => 'erni.ending',
            'USER0009' => 'spfi_ua',
        ];
    }

    /**
     * @param  array<string, string>  $legacyCanvasserMap
     * @return array<string, int>
     */
    protected function resolveMappedCanvasserIds(array $legacyCanvasserMap): array
    {
        $usernameToLegacyCode = [];

        foreach ($legacyCanvasserMap as $legacyCode => $username) {
            $usernameToLegacyCode[strtolower($username)] = strtoupper($legacyCode);
        }

        $usersByUsername = DB::table('users')
            ->whereIn('username', array_values($legacyCanvasserMap))
            ->pluck('id', 'username')
            ->all();

        $mapped = [];

        foreach ($usersByUsername as $username => $id) {
            $legacyCode = $usernameToLegacyCode[strtolower((string) $username)] ?? null;
            if ($legacyCode === null) {
                continue;
            }

            $mapped[$legacyCode] = (int) $id;
        }

        foreach ($legacyCanvasserMap as $legacyCode => $username) {
            if (!isset($mapped[strtoupper($legacyCode)])) {
                $this->warn("Mapped canvasser username '{$username}' for legacy code '{$legacyCode}' not found in users table");
            }
        }

        return $mapped;
    }

    protected function normalizeLegacyCanvasserCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));
        if ($normalized === '' || $normalized === 'NULL') {
            return null;
        }

        return $normalized;
    }

    /**
     * Retry one row upsert when SQL Server returns deadlock/connection-drop errors.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    protected function upsertPrsItemWithRetry(array $attributes, array $values, string $prsNumber, string $productCode): bool
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                DB::table('prs_items')->updateOrInsert($attributes, $values);

                return true;
            } catch (QueryException $e) {
                $message = strtolower((string) $e->getMessage());
                $isDeadlock = str_contains($message, 'deadlock') || str_contains($message, 'sqlstate[40001]');
                $isConnectionDrop = str_contains($message, 'sqlstate[08s01]') || str_contains($message, 'communication link failure');
                $isRetryable = $this->isSqlServer() && ($isDeadlock || $isConnectionDrop);

                if (! $isRetryable || $attempt === $maxAttempts) {
                    $this->warn("PRS Item failed after {$attempt} attempt(s): prsnumber '{$prsNumber}', productcode '{$productCode}'. {$e->getMessage()}");

                    return false;
                }

                $this->warn("Retrying PRS Item upsert attempt {$attempt} for prsnumber '{$prsNumber}', productcode '{$productCode}' due to SQL Server lock/connection issue.");

                if ($isConnectionDrop) {
                    DB::disconnect();
                    DB::reconnect();
                }
            }
        }

        return false;
    }

    protected function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

}
