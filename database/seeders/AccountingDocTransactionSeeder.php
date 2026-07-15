<?php

namespace Database\Seeders;

use App\Models\AccountingCode;
use App\Models\AccountingDocTransaction;
use App\Models\AccountingDocTransactionLine;
use App\Models\Delivery;
use App\Models\ReceivingReport;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccountingDocTransactionSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * @var list<string>
     */
    private const DOC_TYPES = ['RR', 'DR'];

    public function run(): void
    {
        $this->command?->info('Importing Accounting Doc Transactions (RR/DR)...');

        $headers = $this->loadHeaders();
        if ($headers === []) {
            $this->command?->warn('No DocTran headers found for RR/DR.');

            return;
        }

        $this->command?->info('Headers loaded: '.count($headers));

        $rrByNumber = $this->buildNumberLookup(
            ReceivingReport::query()->pluck('id', 'rr_number')->all()
        );
        $drByNumber = $this->buildNumberLookup(
            Delivery::query()->pluck('id', 'dr_number')->all()
        );

        $accountDescriptions = AccountingCode::query()
            ->get(['code', 'desc'])
            ->mapWithKeys(fn (AccountingCode $code): array => [
                trim((string) $code->code) => trim((string) $code->desc),
            ])
            ->all();

        $legacyIdToLocalId = [];
        $inserted = 0;
        $skipped = 0;

        foreach (array_chunk($headers, 200) as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $rrByNumber,
                $drByNumber,
                &$legacyIdToLocalId,
                &$inserted,
                &$skipped,
            ): void {
                foreach ($chunk as $header) {
                    $docType = strtoupper(trim((string) ($header['doc_code'] ?? '')));
                    $docNumber = trim((string) ($header['doc_ref_no'] ?? ''));
                    $legacyTranId = (int) ($header['doc_tran_id'] ?? 0);

                    if ($docType === '' || $docNumber === '' || $legacyTranId <= 0) {
                        $skipped++;

                        continue;
                    }

                    [$sourceType, $sourceId] = $this->resolveSource(
                        $docType,
                        $docNumber,
                        $rrByNumber,
                        $drByNumber,
                    );

                    $transaction = AccountingDocTransaction::query()->updateOrCreate(
                        [
                            'doc_type' => $docType,
                            'doc_number' => $docNumber,
                        ],
                        [
                            'source_type' => $sourceType,
                            'source_id' => $sourceId,
                            'doc_date' => $header['doc_date'] ?? now()->toDateString(),
                            'po_number' => $header['doc_other_ref_no'] ?: null,
                            'supplier_code' => null,
                            'supplier_name' => null,
                            'cost_code_total' => (float) ($header['cost_code_total'] ?? 0),
                            'acct_code_total' => (float) ($header['acct_code_total'] ?? 0),
                            'total_debit' => 0,
                            'total_credit' => 0,
                            'variance' => 0,
                            'status' => 'encoded',
                            'legacy_tran_id' => $legacyTranId,
                        ],
                    );

                    $transaction->lines()->delete();
                    $legacyIdToLocalId[$legacyTranId] = $transaction->id;
                    $inserted++;
                }
            });
        }

        $this->command?->info("Headers upserted: {$inserted} (skipped: {$skipped})");

        $lineCount = $this->importDetails($legacyIdToLocalId, $accountDescriptions);
        $this->command?->info("Detail lines imported: {$lineCount}");

        $this->recalculateImportedTotals(array_values($legacyIdToLocalId));
        $this->command?->info('Accounting Doc Transactions import complete.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadHeaders(): array
    {
        $legacyRows = $this->resolveRows('doc_tran', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && $legacyRows !== []) {
            $this->logImportSource('doc_tran', 'legacy');
            $headers = [];

            foreach ($legacyRows as $row) {
                $mapped = $this->mapHeaderRow($row);
                if ($mapped !== null) {
                    $headers[] = $mapped;
                }
            }

            $this->command?->info('ℹ [doc_tran] rows loaded: '.count($legacyRows).' (filtered: '.count($headers).')');

            return $headers;
        }

        $this->logImportSource('doc_tran', 'csv');
        $csvFile = $this->csvPathFor('doc_tran');

        if (! file_exists($csvFile)) {
            $this->command?->warn("File not found: {$csvFile}");

            return [];
        }

        $headers = [];
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $this->command?->warn("Failed to open file: {$csvFile}");

            return [];
        }

        $columnIndex = $this->buildCsvHeaderIndex(fgetcsv($handle, 0, ';') ?: []);

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $assoc = $this->csvRowToAssoc($row, $columnIndex);
            $mapped = $this->mapHeaderRow($assoc);
            if ($mapped !== null) {
                $headers[] = $mapped;
            }
        }

        fclose($handle);
        $this->command?->info('ℹ [doc_tran] filtered CSV headers: '.count($headers));

        return $headers;
    }

    /**
     * @param  array<int, int>  $legacyIdToLocalId
     * @param  array<string, string>  $accountDescriptions
     */
    private function importDetails(array $legacyIdToLocalId, array $accountDescriptions): int
    {
        if ($legacyIdToLocalId === []) {
            return 0;
        }

        if ($this->isLegacySource()) {
            try {
                return $this->importDetailsFromLegacy($legacyIdToLocalId, $accountDescriptions);
            } catch (Throwable $e) {
                if (! $this->shouldFallbackToLocal()) {
                    throw $e;
                }

                $this->command?->warn('Legacy details failed, falling back to CSV. '.$e->getMessage());
            }
        }

        return $this->importDetailsFromCsv($legacyIdToLocalId, $accountDescriptions);
    }

    /**
     * @param  array<int, int>  $legacyIdToLocalId
     * @param  array<string, string>  $accountDescriptions
     */
    private function importDetailsFromLegacy(array $legacyIdToLocalId, array $accountDescriptions): int
    {
        $this->logImportSource('doc_tran_details', 'legacy');
        $registry = $this->legacyImportRegistry();
        $connection = $registry->resolveConnection('doc_tran_details');
        $table = $registry->resolveTable('doc_tran_details');
        $orderBy = $registry->resolveOrderBy('doc_tran_details');
        $lineCount = 0;
        $legacyIds = array_keys($legacyIdToLocalId);

        foreach (array_chunk($legacyIds, 500) as $idChunk) {
            $lineNoByTxn = [];

            DB::connection($connection)
                ->table($table)
                ->whereIn('DocTranID', $idChunk)
                ->orderBy($orderBy)
                ->chunkById(1000, function ($rows) use (
                    $legacyIdToLocalId,
                    $accountDescriptions,
                    &$lineCount,
                    &$lineNoByTxn,
                ): void {
                    $payload = [];

                    foreach ($rows as $row) {
                        $assoc = (array) $row;
                        $legacyTranId = (int) ($assoc['DocTranID'] ?? $assoc['DocTranId'] ?? 0);
                        $localId = $legacyIdToLocalId[$legacyTranId] ?? null;
                        if ($localId === null) {
                            continue;
                        }

                        $lineNoByTxn[$localId] = ($lineNoByTxn[$localId] ?? 0) + 1;
                        $payload[] = $this->buildLinePayload(
                            $localId,
                            $lineNoByTxn[$localId],
                            $assoc,
                            $accountDescriptions,
                        );
                    }

                    $this->insertLinePayloads($payload);
                    $lineCount += count($payload);
                }, $orderBy);
        }

        return $lineCount;
    }

    /**
     * @param  array<int, int>  $legacyIdToLocalId
     * @param  array<string, string>  $accountDescriptions
     */
    private function importDetailsFromCsv(array $legacyIdToLocalId, array $accountDescriptions): int
    {
        $this->logImportSource('doc_tran_details', 'csv');
        $csvFile = $this->csvPathFor('doc_tran_details');

        if (! file_exists($csvFile)) {
            $this->command?->warn("File not found: {$csvFile}");

            return 0;
        }

        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $this->command?->warn("Failed to open file: {$csvFile}");

            return 0;
        }

        $columnIndex = $this->buildCsvHeaderIndex(fgetcsv($handle, 0, ';') ?: []);
        $lineCount = 0;
        $buffer = [];
        $lineNoByTxn = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $assoc = $this->csvRowToAssoc($row, $columnIndex);
            $legacyTranId = (int) ($assoc['DocTranID'] ?? $assoc['DocTranId'] ?? $assoc['doctranid'] ?? 0);
            $localId = $legacyIdToLocalId[$legacyTranId] ?? null;
            if ($localId === null) {
                continue;
            }

            $lineNoByTxn[$localId] = ($lineNoByTxn[$localId] ?? 0) + 1;
            $buffer[] = $this->buildLinePayload(
                $localId,
                $lineNoByTxn[$localId],
                $assoc,
                $accountDescriptions,
            );

            if (count($buffer) >= 1000) {
                $this->insertLinePayloads($buffer);
                $lineCount += count($buffer);
                $buffer = [];
            }
        }

        fclose($handle);

        if ($buffer !== []) {
            $this->insertLinePayloads($buffer);
            $lineCount += count($buffer);
        }

        return $lineCount;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    private function insertLinePayloads(array $payload): void
    {
        if ($payload === []) {
            return;
        }

        // SQL Server caps bound parameters at 2100 per query.
        $columns = count($payload[0]);
        $chunkSize = DB::connection()->getDriverName() === 'sqlsrv'
            ? max(1, intdiv(2000, max(1, $columns)))
            : 500;

        foreach (array_chunk($payload, $chunkSize) as $chunk) {
            AccountingDocTransactionLine::query()->insert($chunk);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $accountDescriptions
     * @return array<string, mixed>
     */
    private function buildLinePayload(
        int $transactionId,
        int $lineNo,
        array $row,
        array $accountDescriptions,
    ): array {
        $accountCode = trim((string) ($this->readValue($row, [
            'AccountingCode',
            'accounting_code',
            'Accountingcode',
        ]) ?? ''));
        $groupCode = trim((string) ($this->readValue($row, [
            'GroupCode',
            'group_code',
            'Groupcode',
        ]) ?? ''));
        $remarks = trim((string) ($this->readValue($row, ['Remarks', 'remarks']) ?? ''));
        $description = $remarks !== ''
            ? $remarks
            : ($accountDescriptions[$accountCode] ?? null);

        $now = now();

        return [
            'accounting_doc_transaction_id' => $transactionId,
            'line_no' => $lineNo,
            'group_code' => $groupCode !== '' ? $groupCode : null,
            'account_code' => $accountCode,
            'description' => $description,
            'debit' => round((float) ($this->readValue($row, ['Debit', 'debit']) ?? 0), 4),
            'credit' => round((float) ($this->readValue($row, ['Credit', 'credit']) ?? 0), 4),
            'legacy_detail_id' => (int) ($this->readValue($row, ['ID', 'Id', 'id']) ?? 0) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<int>  $transactionIds
     */
    private function recalculateImportedTotals(array $transactionIds): void
    {
        foreach (array_chunk($transactionIds, 200) as $chunk) {
            $transactions = AccountingDocTransaction::query()
                ->with('lines')
                ->whereIn('id', $chunk)
                ->get();

            foreach ($transactions as $transaction) {
                $totalDebit = round((float) $transaction->lines->sum('debit'), 4);
                $totalCredit = round((float) $transaction->lines->sum('credit'), 4);

                // Keep cost_code_total / acct_code_total from legacy header (code checksums, not money).
                $transaction->update([
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'variance' => round($totalDebit - $totalCredit, 4),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapHeaderRow(array $row): ?array
    {
        $docCode = strtoupper(trim((string) ($this->readValue($row, [
            'DocCode',
            'doc_code',
            'Doccode',
        ]) ?? '')));

        if (! in_array($docCode, self::DOC_TYPES, true)) {
            return null;
        }

        $docTranId = (int) ($this->readValue($row, ['DocTranId', 'DocTranID', 'doctranid']) ?? 0);
        $docRefNo = trim((string) ($this->readValue($row, ['DocRefNo', 'doc_ref_no', 'Docrefno']) ?? ''));

        if ($docTranId <= 0 || $docRefNo === '') {
            return null;
        }

        $otherRef = trim((string) ($this->readValue($row, [
            'DocOtherRefNo',
            'doc_other_ref_no',
        ]) ?? ''));
        if ($otherRef === '' || $otherRef === '0') {
            $otherRef = '';
        }

        $docDate = $this->readValue($row, ['DocDate', 'doc_date']);

        return [
            'doc_tran_id' => $docTranId,
            'doc_code' => $docCode,
            'doc_ref_no' => $docRefNo,
            'doc_date' => $docDate ?: now()->toDateString(),
            'doc_other_ref_no' => $otherRef,
            'cost_code_total' => (float) ($this->readValue($row, ['CostCodeTotal', 'cost_code_total']) ?? 0),
            'acct_code_total' => (float) ($this->readValue($row, ['AcctCodeTotal', 'acct_code_total']) ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $rrByNumber
     * @param  array<string, int>  $tsByNumber
     * @param  array<string, int>  $drByNumber
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveSource(
        string $docType,
        string $docNumber,
        array $rrByNumber,
        array $drByNumber,
    ): array {
        return match ($docType) {
            'RR' => isset($rrByNumber[$docNumber])
                ? [ReceivingReport::class, $rrByNumber[$docNumber]]
                : [null, null],
            'DR' => isset($drByNumber[$docNumber])
                ? [Delivery::class, $drByNumber[$docNumber]]
                : [null, null],
            default => [null, null],
        };
    }

    /**
     * @param  array<string|int, mixed>  $map
     * @return array<string, int>
     */
    private function buildNumberLookup(array $map): array
    {
        $lookup = [];
        foreach ($map as $number => $id) {
            $normalized = trim((string) $number);
            if ($normalized !== '') {
                $lookup[$normalized] = (int) $id;
            }
        }

        return $lookup;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    private function buildCsvHeaderIndex(array $headerRow): array
    {
        $index = [];
        foreach ($headerRow as $i => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') {
                $index[$key] = $i;
            }
        }

        return $index;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columnIndex
     * @return array<string, mixed>
     */
    private function csvRowToAssoc(array $row, array $columnIndex): array
    {
        $assoc = [];
        foreach ($columnIndex as $name => $index) {
            $assoc[$name] = $row[$index] ?? null;
        }

        return $assoc;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function readValue(array $row, array $keys): ?string
    {
        $keyLookup = [];
        foreach ($row as $k => $v) {
            if (! is_string($k)) {
                continue;
            }

            $lower = strtolower($k);
            $compact = preg_replace('/[^a-z0-9]/', '', $lower) ?? $lower;
            $keyLookup[$lower] = $v;
            $keyLookup[$compact] = $v;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = trim((string) ($row[$key] ?? ''));

                return $value === '' ? null : $value;
            }

            $lowerKey = strtolower($key);
            $compactKey = preg_replace('/[^a-z0-9]/', '', $lowerKey) ?? $lowerKey;

            if (array_key_exists($lowerKey, $keyLookup)) {
                $value = trim((string) ($keyLookup[$lowerKey] ?? ''));

                return $value === '' ? null : $value;
            }

            if (array_key_exists($compactKey, $keyLookup)) {
                $value = trim((string) ($keyLookup[$compactKey] ?? ''));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }
}
