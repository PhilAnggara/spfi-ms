<?php

namespace App\Services\Reconcile;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ImsNumberAlignAuditor
{
    /**
     * @param  list<string>|null  $only
     * @return array{
     *     since: string,
     *     generated_at: string,
     *     datasets: array<string, array{
     *         actions: list<array<string, mixed>>,
     *         action_counts: array<string, int>,
     *         ims_since_count: int,
     *         spfi_since_count: int
     *     }>,
     *     report_dir: string|null
     * }
     */
    public function audit(string $since, ?array $only = null, bool $writeCsv = true): array
    {
        $datasets = $this->targetDatasets($only);
        $results = [];

        foreach ($datasets as $dataset) {
            $results[$dataset] = $this->auditDataset($dataset, $since);
        }

        $reportDir = null;
        if ($writeCsv) {
            $reportDir = $this->writeCsvReports($since, $results);
        }

        return [
            'since' => $since,
            'generated_at' => now()->toDateTimeString(),
            'datasets' => $results,
            'report_dir' => $reportDir,
        ];
    }

    /**
     * @param  list<string>|null  $only
     * @return list<string>
     */
    public function targetDatasets(?array $only = null): array
    {
        $default = ['prs', 'po', 'sws', 'rr', 'ts', 'dr'];

        if ($only === null || $only === []) {
            return $default;
        }

        return array_values(array_intersect($default, $only));
    }

    /**
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     action_counts: array<string, int>,
     *     ims_since_count: int,
     *     spfi_since_count: int
     * }
     */
    public function auditDataset(string $dataset, string $since): array
    {
        $config = $this->config($dataset);
        $imsDocuments = $this->imsDocuments($dataset, $since);
        $spfiDocuments = $this->spfiDocuments($dataset, $since);

        $spfiByNormalizedNumber = $spfiDocuments
            ->keyBy(fn (array $document): string => DocumentFingerprint::normalizeKey($document['number']));

        $spfiByFingerprint = $spfiDocuments
            ->groupBy(fn (array $document): string => (string) $document['fingerprint']);

        $imsFingerprintCounts = $imsDocuments
            ->countBy(fn (array $document): string => (string) $document['fingerprint']);

        $actions = [];

        foreach ($imsDocuments as $imsDocument) {
            $normalizedNumber = DocumentFingerprint::normalizeKey($imsDocument['number']);
            $exact = $spfiByNormalizedNumber->get($normalizedNumber);

            if (is_array($exact) && $exact['fingerprint'] === $imsDocument['fingerprint']) {
                $fingerprintIsAmbiguousInIms = (int) ($imsFingerprintCounts[$imsDocument['fingerprint']] ?? 0) > 1;

                if (! $fingerprintIsAmbiguousInIms) {
                    $orphans = $this->orphanAliasesOf($spfiDocuments, $imsDocument['number'], (int) $exact['id']);

                    if ($orphans->isNotEmpty()) {
                        foreach ($orphans as $orphan) {
                            $actions[] = $this->buildAction(
                                $dataset,
                                'retire_orphan_alias',
                                $imsDocument,
                                $exact,
                                $orphan,
                                'IMS number already matches an active SPFI document; retiring a leftover reconcile alias that still occupies another number.'
                            );
                        }

                        continue;
                    }
                }

                $actions[] = $this->buildAction($dataset, 'already_match', $imsDocument, $exact, null, 'IMS number already matches an active SPFI document.');

                continue;
            }

            /** @var Collection<int, array<string, mixed>> $candidates */
            $candidates = collect($spfiByFingerprint->get($imsDocument['fingerprint'], []))
                ->values();

            $fingerprintIsAmbiguousInIms = (int) ($imsFingerprintCounts[$imsDocument['fingerprint']] ?? 0) > 1;

            if ($candidates->isEmpty()) {
                $actions[] = $this->buildAction(
                    $dataset,
                    is_array($exact) ? 'replace_from_ims' : 'import_missing',
                    $imsDocument,
                    is_array($exact) ? $exact : null,
                    null,
                    is_array($exact)
                        ? 'IMS number exists in SPFI with different content and no same-content candidate was found.'
                        : 'Document exists in IMS but no same-content SPFI document was found.'
                );

                continue;
            }

            if ($fingerprintIsAmbiguousInIms && ! is_array($exact)) {
                $actions[] = $this->buildAction(
                    $dataset,
                    'manual_review',
                    $imsDocument,
                    null,
                    null,
                    'The same fingerprint appears on multiple IMS document numbers, so auto-alignment would be ambiguous.'
                );

                continue;
            }

            if ($candidates->count() === 1) {
                $canonical = $candidates->first();
                if ($fingerprintIsAmbiguousInIms && $canonical['number'] !== $imsDocument['number']) {
                    $actions[] = $this->buildAction(
                        $dataset,
                        'manual_review',
                        $imsDocument,
                        $canonical,
                        is_array($exact) && $exact['id'] !== $canonical['id'] ? $exact : null,
                        'The same fingerprint appears on multiple IMS numbers, so the SPFI candidate cannot be auto-renamed safely.'
                    );

                    continue;
                }

                $action = $canonical['number'] === $imsDocument['number']
                    ? 'already_match'
                    : ($canonical['is_alias'] ? 'promote_alias' : 'rename_to_ims');

                $actions[] = $this->buildAction(
                    $dataset,
                    $action,
                    $imsDocument,
                    $canonical,
                    is_array($exact) && $exact['id'] !== $canonical['id'] ? $exact : null,
                    $canonical['is_alias']
                        ? 'Alias/imported SPFI document matches IMS content and should be promoted to the IMS number.'
                        : 'Active SPFI document matches IMS content but is using a different number.'
                );

                continue;
            }

            $aliasCandidate = $candidates->first(function (array $candidate) use ($imsDocument): bool {
                return $candidate['is_alias']
                    && (
                        ($candidate['aliased_from'] !== null
                            && DocumentFingerprint::normalizeKey((string) $candidate['aliased_from']) === DocumentFingerprint::normalizeKey($imsDocument['number']))
                        || ($candidate['legacy_number'] !== null
                            && DocumentFingerprint::normalizeKey((string) $candidate['legacy_number']) === DocumentFingerprint::normalizeKey($imsDocument['number']))
                    );
            });

            if (is_array($aliasCandidate)) {
                $actions[] = $this->buildAction(
                    $dataset,
                    'promote_alias',
                    $imsDocument,
                    $aliasCandidate,
                    is_array($exact) && $exact['id'] !== $aliasCandidate['id'] ? $exact : null,
                    'Multiple SPFI rows share the fingerprint, but one reconcile alias clearly maps back to the IMS number.'
                );

                continue;
            }

            $actions[] = $this->buildAction(
                $dataset,
                'manual_review',
                $imsDocument,
                null,
                is_array($exact) ? $exact : null,
                'Multiple active SPFI documents share the same IMS fingerprint; review is required before renumbering.'
            );
        }

        $actionCounts = collect($actions)
            ->countBy(fn (array $action): string => (string) $action['action'])
            ->sortKeys()
            ->all();

        return [
            'actions' => $actions,
            'action_counts' => $actionCounts,
            'ims_since_count' => $imsDocuments->count(),
            'spfi_since_count' => $spfiDocuments->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function imsDocuments(string $dataset, string $since): Collection
    {
        $config = $this->config($dataset);

        return $this->legacy()
            ->table($config['legacy_table'])
            ->where($config['legacy_date'], '>=', $since)
            ->orderBy($config['legacy_date'])
            ->orderBy($config['legacy_number'])
            ->pluck($config['legacy_number'])
            ->map(fn ($number): string => trim((string) $number))
            ->filter()
            ->unique(fn (string $number): string => DocumentFingerprint::normalizeKey($number))
            ->values()
            ->map(function (string $number) use ($dataset): array {
                return [
                    'number' => $number,
                    'normalized_number' => DocumentFingerprint::normalizeKey($number),
                    'fingerprint' => $this->imsFingerprint($dataset, $number),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function spfiDocuments(string $dataset, string $since): Collection
    {
        $config = $this->config($dataset);
        $query = DB::table($config['spfi_table'])
            ->where($config['spfi_date'], '>=', $since);

        if ($config['soft_deletes'] && Schema::hasColumn($config['spfi_table'], 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $rows = $query
            ->orderBy($config['spfi_date'])
            ->orderBy('id')
            ->get(['id', $config['spfi_number'].' as number', 'meta']);

        return $rows->map(function ($row) use ($dataset): array {
            $meta = $this->decodeMeta($row->meta ?? null);

            return [
                'id' => (int) $row->id,
                'number' => trim((string) $row->number),
                'normalized_number' => DocumentFingerprint::normalizeKey($row->number),
                'fingerprint' => $this->spfiFingerprintById($dataset, (int) $row->id),
                'meta' => $meta,
                'aliased_from' => $meta['aliased_from'] ?? null,
                'legacy_number' => $this->legacyNumberFromMeta($dataset, $meta),
                'is_alias' => $this->isAliasDocument($dataset, trim((string) $row->number), $meta),
            ];
        })->values();
    }

    /**
     * Active reconcile aliases that still point at an IMS number whose canonical SPFI row already exists.
     *
     * @param  Collection<int, array<string, mixed>>  $spfiDocuments
     * @return Collection<int, array<string, mixed>>
     */
    private function orphanAliasesOf(Collection $spfiDocuments, string $imsNumber, int $canonicalId): Collection
    {
        $normalizedIms = DocumentFingerprint::normalizeKey($imsNumber);

        return $spfiDocuments
            ->filter(function (array $document) use ($normalizedIms, $canonicalId): bool {
                if ((int) $document['id'] === $canonicalId) {
                    return false;
                }

                if (! ($document['is_alias'] ?? false)) {
                    return false;
                }

                $aliasedFrom = $document['aliased_from'] !== null
                    ? DocumentFingerprint::normalizeKey((string) $document['aliased_from'])
                    : null;
                $legacyNumber = $document['legacy_number'] !== null
                    ? DocumentFingerprint::normalizeKey((string) $document['legacy_number'])
                    : null;

                return $aliasedFrom === $normalizedIms || $legacyNumber === $normalizedIms;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $canonical
     * @param  array<string, mixed>|null  $retired
     * @return array<string, mixed>
     */
    private function buildAction(
        string $dataset,
        string $action,
        array $imsDocument,
        ?array $canonical,
        ?array $retired,
        string $reason,
    ): array {
        return [
            'document_type' => $dataset,
            'action' => $action,
            'ims_number' => $imsDocument['number'],
            'ims_fingerprint' => $imsDocument['fingerprint'],
            'spfi_id' => $canonical['id'] ?? null,
            'spfi_number' => $canonical['number'] ?? null,
            'spfi_is_alias' => $canonical['is_alias'] ?? false,
            'retire_spfi_id' => $retired['id'] ?? null,
            'retire_spfi_number' => $retired['number'] ?? null,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeMeta(mixed $meta): ?array
    {
        if (! is_string($meta) || trim($meta) === '') {
            return null;
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function legacyNumberFromMeta(string $dataset, ?array $meta): ?string
    {
        if (! is_array($meta)) {
            return null;
        }

        return match ($dataset) {
            'prs' => $meta['legacy_prsnumber'] ?? null,
            'po' => $meta['legacy_po_code'] ?? null,
            'rr' => $meta['legacy_rr_code'] ?? null,
            'sws' => $meta['legacy_sws_code'] ?? null,
            'ts' => $meta['legacy_ts_code'] ?? null,
            'dr' => $meta['legacy_dr_code'] ?? null,
            default => null,
        };
    }

    private function isAliasDocument(string $dataset, string $number, ?array $meta): bool
    {
        if (is_array($meta)) {
            $aliasedFrom = trim((string) ($meta['aliased_from'] ?? ''));
            if ($aliasedFrom !== '') {
                return true;
            }
        }

        if (! Schema::hasTable('reconciliation_number_maps')) {
            return false;
        }

        return DB::table('reconciliation_number_maps')
            ->where('document_type', $dataset)
            ->where('spfi_number', $number)
            ->where('resolution', 'import_as_alias')
            ->exists();
    }

    private function imsFingerprint(string $dataset, string $number): string
    {
        return $this->fingerprints($dataset, $number)['ims'];
    }

    private function spfiFingerprintById(string $dataset, int $id): string
    {
        return match ($dataset) {
            'prs' => $this->spfiPrsFingerprintById($id),
            'po' => $this->spfiPoFingerprintById($id),
            'rr' => $this->spfiRrFingerprintById($id),
            'sws' => $this->spfiSwsFingerprintById($id),
            'ts' => $this->spfiTsFingerprintById($id),
            'dr' => $this->spfiDrFingerprintById($id),
            default => '',
        };
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function fingerprints(string $dataset, string $number): array
    {
        return match ($dataset) {
            'prs' => $this->prsFingerprint($number),
            'po' => $this->poFingerprint($number),
            'rr' => $this->rrFingerprint($number),
            'sws' => $this->swsFingerprint($number),
            'ts' => $this->tsFingerprint($number),
            'dr' => $this->drFingerprint($number),
            default => ['ims' => '', 'spfi' => null],
        };
    }

    /**
     * @return array{legacy_table: string, legacy_number: string, legacy_date: string, spfi_table: string, spfi_number: string, spfi_date: string, soft_deletes: bool}
     */
    private function config(string $dataset): array
    {
        return match ($dataset) {
            'prs' => [
                'legacy_table' => 'prs',
                'legacy_number' => 'prsnumber',
                'legacy_date' => 'created_date',
                'spfi_table' => 'prs',
                'spfi_number' => 'prs_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => true,
            ],
            'po' => [
                'legacy_table' => 'po',
                'legacy_number' => 'po_code',
                'legacy_date' => 'created_date',
                'spfi_table' => 'purchase_orders',
                'spfi_number' => 'po_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => true,
            ],
            'rr' => [
                'legacy_table' => 'rr',
                'legacy_number' => 'rr_code',
                'legacy_date' => 'created_date',
                'spfi_table' => 'receiving_reports',
                'spfi_number' => 'rr_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => true,
            ],
            'sws' => [
                'legacy_table' => 'sws',
                'legacy_number' => 'sws_code',
                'legacy_date' => 'created_date',
                'spfi_table' => 'store_withdrawals',
                'spfi_number' => 'sws_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => true,
            ],
            'ts' => [
                'legacy_table' => 'ts',
                'legacy_number' => 'ts_code',
                'legacy_date' => 'created_date',
                'spfi_table' => 'transfer_slips',
                'spfi_number' => 'ts_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => true,
            ],
            'dr' => [
                'legacy_table' => 'dr',
                'legacy_number' => 'dr_code',
                'legacy_date' => 'created_date',
                'spfi_table' => 'deliveries',
                'spfi_number' => 'dr_number',
                'spfi_date' => 'created_at',
                'soft_deletes' => Schema::hasColumn('deliveries', 'deleted_at'),
            ],
            default => throw new \InvalidArgumentException("Unsupported align dataset [{$dataset}]."),
        };
    }

    private function legacy(): \Illuminate\Database\Connection
    {
        return DB::connection((string) config('reconcile.legacy_connection'));
    }

    private function prsFingerprint(string $number): array
    {
        $legLines = $this->legacy()->table('prs_detail')
            ->where('prsnumber', $number)
            ->get(['productcode', 'qty']);

        $ims = DocumentFingerprint::linesSignature(
            $legLines->map(fn ($row) => ['code' => (string) $row->productcode, 'qty' => $row->qty])->all()
        );

        $prsId = DB::table('prs')->where('prs_number', $number)->whereNull('deleted_at')->value('id');
        if (! $prsId) {
            return ['ims' => DocumentFingerprint::hash($ims), 'spfi' => null];
        }

        return [
            'ims' => DocumentFingerprint::hash($ims),
            'spfi' => $this->spfiPrsFingerprintById((int) $prsId),
        ];
    }

    private function spfiPrsFingerprintById(int $prsId): string
    {
        $lines = DB::table('prs_items as item')
            ->join('items as i', 'i.id', '=', 'item.item_id')
            ->where('item.prs_id', $prsId)
            ->get(['i.code', 'item.quantity']);

        return DocumentFingerprint::hash(
            DocumentFingerprint::linesSignature(
                $lines->map(fn ($row) => ['code' => (string) $row->code, 'qty' => $row->quantity])->all()
            )
        );
    }

    private function poFingerprint(string $number): array
    {
        $legacy = $this->legacy()->table('po')->where('po_code', $number)->first();
        $legacyProducts = $this->legacy()->table('po_detail')
            ->where('po_code', $number)
            ->where('is_active', 'Y')
            ->pluck('product_code')
            ->map(fn ($value) => DocumentFingerprint::normalizeKey($value))
            ->sort()
            ->values()
            ->implode(',');

        $ims = DocumentFingerprint::compose([
            'supplier' => $legacy->supplier_code ?? '',
            'products' => $legacyProducts,
        ]);

        $poId = DB::table('purchase_orders')->where('po_number', $number)->whereNull('deleted_at')->value('id');
        if (! $poId) {
            return ['ims' => $ims, 'spfi' => null];
        }

        return [
            'ims' => $ims,
            'spfi' => $this->spfiPoFingerprintById((int) $poId),
        ];
    }

    private function spfiPoFingerprintById(int $poId): string
    {
        $po = DB::table('purchase_orders')->where('id', $poId)->first();
        $supplierCode = DB::table('suppliers')->where('id', $po->supplier_id)->value('code');
        $products = DB::table('purchase_order_items as item')
            ->join('items as i', 'i.id', '=', 'item.item_id')
            ->where('item.purchase_order_id', $poId)
            ->pluck('i.code')
            ->map(fn ($value) => DocumentFingerprint::normalizeKey($value))
            ->sort()
            ->values()
            ->implode(',');

        return DocumentFingerprint::compose([
            'supplier' => $supplierCode ?? '',
            'products' => $products,
        ]);
    }

    private function rrFingerprint(string $number): array
    {
        $legacy = $this->legacy()->table('rr')->where('rr_code', $number)->first();
        $qtyGood = (float) $this->legacy()->table('rr_detail')->where('rr_code', $number)->where('is_active', 'Y')->sum('qty_g');
        $products = $this->legacy()->table('rr_detail')->where('rr_code', $number)->where('is_active', 'Y')->pluck('product_code')
            ->map(fn ($value) => DocumentFingerprint::normalizeKey($value))
            ->sort()
            ->values()
            ->implode(',');

        $ims = DocumentFingerprint::compose([
            'po' => $legacy->po_code ?? '',
            'qty' => (string) round($qtyGood, 5),
            'products' => $products,
        ]);

        $rrId = DB::table('receiving_reports')->where('rr_number', $number)->whereNull('deleted_at')->value('id');
        if (! $rrId) {
            return ['ims' => $ims, 'spfi' => null];
        }

        return [
            'ims' => $ims,
            'spfi' => $this->spfiRrFingerprintById((int) $rrId),
        ];
    }

    private function spfiRrFingerprintById(int $rrId): string
    {
        $rr = DB::table('receiving_reports')->where('id', $rrId)->first();
        $poNumber = DB::table('purchase_orders')->where('id', $rr->purchase_order_id)->value('po_number');
        $qtyGood = (float) DB::table('receiving_report_items')->where('receiving_report_id', $rrId)->sum('qty_good');
        $products = DB::table('receiving_report_items as item')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'item.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->where('item.receiving_report_id', $rrId)
            ->pluck('i.code')
            ->map(fn ($value) => DocumentFingerprint::normalizeKey($value))
            ->sort()
            ->values()
            ->implode(',');

        return DocumentFingerprint::compose([
            'po' => $poNumber ?? '',
            'qty' => (string) round($qtyGood, 5),
            'products' => $products,
        ]);
    }

    private function swsFingerprint(string $number): array
    {
        $legacyLines = $this->legacy()->table('sws_detail')
            ->where('sws_code', $number)
            ->where('is_active', '!=', 'N')
            ->get(['product_code', 'qty']);
        $ims = DocumentFingerprint::hash(
            DocumentFingerprint::linesSignature(
                $legacyLines->map(fn ($row) => ['code' => (string) $row->product_code, 'qty' => $row->qty])->all()
            )
        );

        $swsId = DB::table('store_withdrawals')->where('sws_number', $number)->whereNull('deleted_at')->value('id');
        if (! $swsId) {
            return ['ims' => $ims, 'spfi' => null];
        }

        return [
            'ims' => $ims,
            'spfi' => $this->spfiSwsFingerprintById((int) $swsId),
        ];
    }

    private function spfiSwsFingerprintById(int $swsId): string
    {
        $lines = DB::table('store_withdrawal_items as item')
            ->join('items as i', 'i.id', '=', 'item.item_id')
            ->where('item.store_withdrawal_id', $swsId)
            ->when(Schema::hasColumn('store_withdrawal_items', 'deleted_at'), fn ($query) => $query->whereNull('item.deleted_at'))
            ->get(['i.code', 'item.quantity']);

        return DocumentFingerprint::hash(
            DocumentFingerprint::linesSignature(
                $lines->map(fn ($row) => ['code' => (string) $row->code, 'qty' => $row->quantity])->all()
            )
        );
    }

    private function tsFingerprint(string $number): array
    {
        $legacy = $this->legacy()->table('ts')->where('ts_code', $number)->first();
        $legacyQty = (float) $this->legacy()->table('ts_detail')
            ->where('ts_code', $number)
            ->where('is_active', '!=', 'N')
            ->sum('qty');
        $ims = DocumentFingerprint::compose([
            'sws' => DocumentFingerprint::normalizeKey($legacy->sws_code ?? ''),
            'qty' => (string) round($legacyQty, 5),
        ]);

        $tsId = DB::table('transfer_slips')->where('ts_number', $number)->whereNull('deleted_at')->value('id');
        if (! $tsId) {
            return ['ims' => $ims, 'spfi' => null];
        }

        return [
            'ims' => $ims,
            'spfi' => $this->spfiTsFingerprintById((int) $tsId),
        ];
    }

    private function spfiTsFingerprintById(int $tsId): string
    {
        $ts = DB::table('transfer_slips')->where('id', $tsId)->first();
        $swsNumber = DB::table('store_withdrawals')->where('id', $ts->store_withdrawal_id)->value('sws_number');
        $qty = (float) DB::table('transfer_slip_items')
            ->where('transfer_slip_id', $tsId)
            ->when(Schema::hasColumn('transfer_slip_items', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->sum('quantity');

        return DocumentFingerprint::compose([
            'sws' => DocumentFingerprint::normalizeKey($swsNumber ?? ''),
            'qty' => (string) round($qty, 5),
        ]);
    }

    private function drFingerprint(string $number): array
    {
        $legacyQty = (float) $this->legacy()->table('dr_detail')->where('dr_code', $number)->sum('dr_qty');
        $ims = DocumentFingerprint::compose(['qty' => (string) round($legacyQty, 5)]);

        $drId = DB::table('deliveries')->where('dr_number', $number)
            ->when(Schema::hasColumn('deliveries', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->value('id');

        if (! $drId) {
            return ['ims' => $ims, 'spfi' => null];
        }

        return [
            'ims' => $ims,
            'spfi' => $this->spfiDrFingerprintById((int) $drId),
        ];
    }

    private function spfiDrFingerprintById(int $drId): string
    {
        $qty = 0.0;
        if (Schema::hasTable('delivery_items')) {
            $qty = (float) DB::table('delivery_items')
                ->where('delivery_id', $drId)
                ->when(Schema::hasColumn('delivery_items', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->sum('quantity');
        }

        return DocumentFingerprint::compose(['qty' => (string) round($qty, 5)]);
    }

    /**
     * @param  array<string, array{actions: list<array<string, mixed>>, action_counts: array<string, int>, ims_since_count: int, spfi_since_count: int}>  $results
     */
    private function writeCsvReports(string $since, array $results): string
    {
        $dir = storage_path('app/'.trim((string) config('reconcile.report_path'), '/\\').'/align_'.now()->format('Ymd_His'));
        File::ensureDirectoryExists($dir);

        foreach ($results as $dataset => $payload) {
            $actions = $payload['actions'] ?? [];
            if ($actions !== []) {
                $this->writeCsv($dir.'/align_'.$dataset.'_actions.csv', $actions);
            }
        }

        File::put($dir.'/summary.json', json_encode([
            'since' => $since,
            'generated_at' => now()->toDateTimeString(),
            'datasets' => collect($results)->map(fn (array $payload) => [
                'ims_since_count' => $payload['ims_since_count'] ?? 0,
                'spfi_since_count' => $payload['spfi_since_count'] ?? 0,
                'action_counts' => $payload['action_counts'] ?? [],
            ])->all(),
        ], JSON_PRETTY_PRINT));

        return $dir;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return;
        }

        $headers = array_keys($rows[0]);
        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line, ';');
        }

        fclose($handle);
    }
}
