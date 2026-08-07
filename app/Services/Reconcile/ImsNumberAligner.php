<?php

namespace App\Services\Reconcile;

use App\Services\Legacy\LegacyIncrementalImporter;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImsNumberAligner
{
    public function __construct(
        private readonly ImsNumberAlignAuditor $auditor,
        private readonly LegacyIncrementalImporter $importer,
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  list<string>|null  $only
     * @return array{
     *     audit: array<string, mixed>,
     *     applied: array<string, int>,
     *     skipped: array<string, int>,
     *     logs: list<array<string, mixed>>
     * }
     */
    public function apply(string $since, ?array $only = null): array
    {
        $audit = $this->auditor->audit($since, $only, writeCsv: true);
        $applied = [];
        $skipped = [];
        $logs = [];

        foreach ($this->targetOrder($only) as $dataset) {
            $actions = $audit['datasets'][$dataset]['actions'] ?? [];
            $applied[$dataset] = 0;
            $skipped[$dataset] = 0;

            foreach ($actions as $action) {
                $result = DB::transaction(function () use ($dataset, $action): array {
                    return $this->applyAction($dataset, $action);
                });

                $logs[] = $result['log'];

                if ($result['applied']) {
                    $applied[$dataset]++;
                } else {
                    $skipped[$dataset]++;
                }
            }
        }

        $this->persistLogs($logs);

        return [
            'audit' => $audit,
            'applied' => $applied,
            'skipped' => $skipped,
            'logs' => $logs,
        ];
    }

    /**
     * @param  list<string>|null  $only
     * @return list<string>
     */
    private function targetOrder(?array $only = null): array
    {
        $default = ['prs', 'po', 'sws', 'rr', 'ts', 'dr'];

        if ($only === null || $only === []) {
            return $default;
        }

        return array_values(array_intersect($default, $only));
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function applyAction(string $dataset, array $action): array
    {
        $imsNumber = trim((string) ($action['ims_number'] ?? ''));
        $spfiId = (int) ($action['spfi_id'] ?? 0);
        $spfiNumber = trim((string) ($action['spfi_number'] ?? ''));
        $retireId = (int) ($action['retire_spfi_id'] ?? 0);
        $resolvedAction = (string) ($action['action'] ?? 'manual_review');

        return match ($resolvedAction) {
            'already_match' => $this->skipLog($dataset, $imsNumber, $spfiId, $spfiNumber, 'already_match'),
            'manual_review' => $this->skipLog($dataset, $imsNumber, $spfiId, $spfiNumber, 'manual_review'),
            'rename_to_ims' => $this->renameDocumentToImsNumber($dataset, $spfiId, $spfiNumber, $imsNumber, $retireId),
            'promote_alias' => $this->promoteAliasToImsNumber($dataset, $spfiId, $spfiNumber, $imsNumber, $retireId),
            'retire_orphan_alias' => $this->retireOrphanAlias(
                $dataset,
                $imsNumber,
                $spfiId,
                $spfiNumber,
                $retireId,
                trim((string) ($action['retire_spfi_number'] ?? '')),
            ),
            'replace_from_ims' => $this->replaceWithImsDocument($dataset, $imsNumber, $retireId ?: $spfiId),
            'import_missing' => $this->importMissingImsDocument($dataset, $imsNumber),
            default => $this->skipLog($dataset, $imsNumber, $spfiId, $spfiNumber, 'unsupported_action'),
        };
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function retireOrphanAlias(
        string $dataset,
        string $imsNumber,
        int $canonicalId,
        string $canonicalNumber,
        int $retireId,
        string $aliasNumber,
    ): array {
        if ($retireId <= 0 || $imsNumber === '' || $retireId === $canonicalId) {
            return $this->skipLog($dataset, $imsNumber, $canonicalId, $canonicalNumber, 'invalid_retire_orphan_payload');
        }

        $releasedNumber = $this->retireActiveDocument($dataset, $retireId);
        $mapAliasNumber = $aliasNumber !== '' ? $aliasNumber : $releasedNumber;

        if (Schema::hasTable('reconciliation_number_maps') && $mapAliasNumber !== '') {
            DB::table('reconciliation_number_maps')
                ->where('document_type', $dataset)
                ->where('ims_number', $imsNumber)
                ->where('spfi_number', $mapAliasNumber)
                ->where('resolution', 'import_as_alias')
                ->update([
                    'resolution' => 'superseded',
                    'updated_at' => now(),
                ]);
        }

        $this->writeCanonicalMap($dataset, $imsNumber, $mapAliasNumber !== '' ? $mapAliasNumber : null, $imsNumber);

        return $this->appliedLog(
            $dataset,
            $imsNumber,
            $canonicalId,
            $canonicalNumber,
            'retired orphan reconcile alias '.$mapAliasNumber
        );
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function renameDocumentToImsNumber(string $dataset, int $spfiId, string $currentNumber, string $imsNumber, int $retireId = 0): array
    {
        if ($spfiId <= 0 || $imsNumber === '') {
            return $this->skipLog($dataset, $imsNumber, $spfiId, $currentNumber, 'invalid_rename_payload');
        }

        [$table, $numberColumn] = $this->tableConfig($dataset);

        if ($retireId > 0 && $retireId !== $spfiId) {
            $this->retireActiveDocument($dataset, $retireId);
        }

        $this->assertTargetNumberIsFree($table, $numberColumn, $imsNumber, $spfiId);

        DB::table($table)
            ->where('id', $spfiId)
            ->update([
                $numberColumn => $imsNumber,
                'updated_at' => now(),
            ]);

        $this->writeCanonicalMap($dataset, $imsNumber, $currentNumber, $currentNumber);

        return $this->appliedLog($dataset, $imsNumber, $spfiId, $imsNumber, 'renamed to IMS number');
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function promoteAliasToImsNumber(string $dataset, int $aliasId, string $aliasNumber, string $imsNumber, int $retireId): array
    {
        if ($aliasId <= 0 || $imsNumber === '') {
            return $this->skipLog($dataset, $imsNumber, $aliasId, $aliasNumber, 'invalid_promote_payload');
        }

        [$table, $numberColumn] = $this->tableConfig($dataset);

        if ($retireId > 0 && $retireId !== $aliasId) {
            $this->retireActiveDocument($dataset, $retireId);
        }

        $this->assertTargetNumberIsFree($table, $numberColumn, $imsNumber, $aliasId);

        $currentMeta = $this->decodeMeta(DB::table($table)->where('id', $aliasId)->value('meta'));
        unset($currentMeta['aliased_from']);
        $currentMeta['reconcile_canonical'] = true;
        if ($retireId > 0) {
            $currentMeta['replaced_spfi_id'] = $retireId;
        }

        DB::table($table)
            ->where('id', $aliasId)
            ->update([
                $numberColumn => $imsNumber,
                'meta' => json_encode($currentMeta),
                'updated_at' => now(),
            ]);

        DB::table('reconciliation_number_maps')
            ->where('document_type', $dataset)
            ->where('spfi_number', $aliasNumber)
            ->where('ims_number', $imsNumber)
            ->update([
                'resolution' => 'superseded',
                'updated_at' => now(),
            ]);

        $this->writeCanonicalMap($dataset, $imsNumber, $aliasNumber, $imsNumber);

        return $this->appliedLog($dataset, $imsNumber, $aliasId, $imsNumber, 'promoted alias to IMS number');
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function replaceWithImsDocument(string $dataset, string $imsNumber, int $retireId): array
    {
        if ($retireId <= 0 || $imsNumber === '') {
            return $this->skipLog($dataset, $imsNumber, $retireId, $imsNumber, 'invalid_replace_payload');
        }

        $retiredNumber = $this->retireActiveDocument($dataset, $retireId);
        $createdId = $this->importer->importCanonicalDocument($dataset, $imsNumber, $imsNumber);

        if ($createdId === null) {
            return $this->skipLog($dataset, $imsNumber, $retireId, $retiredNumber, 'IMS import failed after releasing conflicting SPFI number');
        }

        $this->writeCanonicalMap($dataset, $imsNumber, $retiredNumber, $imsNumber);

        return $this->appliedLog($dataset, $imsNumber, $createdId, $imsNumber, 'retired conflicting SPFI document and re-imported IMS document');
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function importMissingImsDocument(string $dataset, string $imsNumber): array
    {
        if ($imsNumber === '') {
            return $this->skipLog($dataset, $imsNumber, null, null, 'invalid_import_payload');
        }

        $createdId = $this->importer->importCanonicalDocument($dataset, $imsNumber, $imsNumber);

        if ($createdId === null) {
            return $this->skipLog($dataset, $imsNumber, null, $imsNumber, 'IMS import failed');
        }

        $this->writeCanonicalMap($dataset, $imsNumber, null, $imsNumber);

        return $this->appliedLog($dataset, $imsNumber, $createdId, $imsNumber, 'imported missing IMS document');
    }

    private function retireActiveDocument(string $dataset, int $id): string
    {
        [$table, $numberColumn] = $this->tableConfig($dataset);
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            return '';
        }

        $releasedNumber = trim((string) $row->{$numberColumn});

        $referenceType = match ($dataset) {
            'rr' => StockService::REF_RECEIVING_REPORT,
            'ts' => StockService::REF_TRANSFER_SLIP,
            'dr' => StockService::REF_DELIVERY,
            default => null,
        };

        if ($referenceType !== null) {
            $this->stockService->purgeDocumentMovements($referenceType, $id);
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            DB::table($table)
                ->where('id', $id)
                ->update([
                    $numberColumn => 'DELETED-'.$id,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table($table)
                ->where('id', $id)
                ->update([
                    $numberColumn => 'DELETED-'.$id,
                    'updated_at' => now(),
                ]);
        }

        $childMap = [
            'prs' => ['prs_items', 'prs_id'],
            'po' => ['purchase_order_items', 'purchase_order_id'],
            'rr' => ['receiving_report_items', 'receiving_report_id'],
            'sws' => ['store_withdrawal_items', 'store_withdrawal_id'],
            'ts' => ['transfer_slip_items', 'transfer_slip_id'],
            'dr' => ['delivery_items', 'delivery_id'],
        ];

        if (isset($childMap[$dataset])) {
            [$childTable, $foreignKey] = $childMap[$dataset];
            if (Schema::hasTable($childTable) && Schema::hasColumn($childTable, 'deleted_at')) {
                DB::table($childTable)
                    ->where($foreignKey, $id)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        return $releasedNumber;
    }

    private function writeCanonicalMap(string $dataset, string $imsNumber, ?string $existingNumber, string $spfiNumber): void
    {
        if (! Schema::hasTable('reconciliation_number_maps')) {
            return;
        }

        $query = DB::table('reconciliation_number_maps')
            ->where('document_type', $dataset)
            ->where('ims_number', $imsNumber)
            ->where('spfi_number', $spfiNumber);

        if ($query->exists()) {
            $query->update([
                'existing_spfi_number' => $existingNumber,
                'resolution' => 'ims_canonical',
                'ims_fingerprint' => null,
                'spfi_fingerprint' => null,
                'meta' => json_encode([
                    'aligned_at' => now()->toDateTimeString(),
                ]),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('reconciliation_number_maps')->insert([
            'document_type' => $dataset,
            'ims_number' => $imsNumber,
            'spfi_number' => $spfiNumber,
            'existing_spfi_number' => $existingNumber,
            'resolution' => 'ims_canonical',
            'ims_fingerprint' => null,
            'spfi_fingerprint' => null,
            'meta' => json_encode([
                'aligned_at' => now()->toDateTimeString(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function tableConfig(string $dataset): array
    {
        return match ($dataset) {
            'prs' => ['prs', 'prs_number'],
            'po' => ['purchase_orders', 'po_number'],
            'rr' => ['receiving_reports', 'rr_number'],
            'sws' => ['store_withdrawals', 'sws_number'],
            'ts' => ['transfer_slips', 'ts_number'],
            'dr' => ['deliveries', 'dr_number'],
            default => throw new \InvalidArgumentException("Unsupported align dataset [{$dataset}]."),
        };
    }

    private function assertTargetNumberIsFree(string $table, string $numberColumn, string $number, ?int $ignoreId = null): void
    {
        $query = DB::table($table)
            ->where($numberColumn, $number)
            ->when($ignoreId !== null, fn ($inner) => $inner->where('id', '<>', $ignoreId));

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($query->exists()) {
            throw new \RuntimeException("Target number {$number} is still occupied in {$table}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $meta): array
    {
        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function skipLog(string $dataset, string $legacyKey, ?int $newId, ?string $spfiNumber, string $message): array
    {
        return [
            'applied' => false,
            'log' => [
                'dataset' => 'align_'.$dataset,
                'legacy_key' => $legacyKey,
                'action' => 'skip',
                'new_id' => $newId,
                'spfi_number' => $spfiNumber,
                'status' => 'skip',
                'message' => $message,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    /**
     * @return array{applied: bool, log: array<string, mixed>}
     */
    private function appliedLog(string $dataset, string $legacyKey, int $newId, string $spfiNumber, string $message): array
    {
        return [
            'applied' => true,
            'log' => [
                'dataset' => 'align_'.$dataset,
                'legacy_key' => $legacyKey,
                'action' => 'apply',
                'new_id' => $newId,
                'spfi_number' => $spfiNumber,
                'status' => 'imported',
                'message' => $message,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $logs
     */
    private function persistLogs(array $logs): void
    {
        if ($logs === [] || ! Schema::hasTable('reconciliation_import_logs')) {
            return;
        }

        foreach (array_chunk($logs, 200) as $chunk) {
            DB::table('reconciliation_import_logs')->insert($chunk);
        }
    }
}
