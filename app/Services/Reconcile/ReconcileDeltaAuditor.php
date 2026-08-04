<?php

namespace App\Services\Reconcile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReconcileDeltaAuditor
{
    /**
     * @param  list<string>|null  $only
     * @return array{
     *     since: string,
     *     generated_at: string,
     *     datasets: array<string, array<string, mixed>>,
     *     stock_mismatches: list<array<string, mixed>>,
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

        $stockMismatches = in_array('stock', $datasets, true) || $only === null
            ? $this->auditStock()
            : [];

        $reportDir = null;
        if ($writeCsv) {
            $reportDir = $this->writeCsvReports($since, $results, $stockMismatches);
        }

        return [
            'since' => $since,
            'generated_at' => now()->toDateTimeString(),
            'datasets' => $results,
            'stock_mismatches' => $stockMismatches,
            'report_dir' => $reportDir,
        ];
    }

    /**
     * @param  list<string>|null  $only
     * @return list<string>
     */
    public function targetDatasets(?array $only = null): array
    {
        $default = [
            'supplier',
            'product',
            'prs',
            'canvassing',
            'po',
            'rr',
            'sws',
            'ts',
            'dr',
            'stock',
        ];

        if ($only === null || $only === []) {
            return $default;
        }

        return array_values(array_intersect($default, $only));
    }

    /**
     * @return array{
     *     ims_only: list<array<string, mixed>>,
     *     new_only: list<array<string, mixed>>,
     *     content_mismatches: list<array<string, mixed>>,
     *     match_count: int,
     *     ims_since_count: int,
     *     new_since_count: int
     * }
     */
    public function auditDataset(string $dataset, string $since): array
    {
        return match ($dataset) {
            'supplier' => $this->auditByCode(
                legacyTable: 'supplier',
                legacyCode: 'supplier_code',
                legacyDate: 'created_date',
                newTable: 'suppliers',
                newCode: 'code',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
            ),
            'product' => $this->auditByCode(
                legacyTable: 'product',
                legacyCode: 'product_code',
                legacyDate: 'created_date',
                newTable: 'items',
                newCode: 'code',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
            ),
            'prs' => $this->auditDocument(
                type: 'prs',
                legacyTable: 'prs',
                legacyNumber: 'prsnumber',
                legacyDate: 'created_date',
                newTable: 'prs',
                newNumber: 'prs_number',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
                fingerprintFn: fn (string $number) => $this->prsFingerprint($number),
            ),
            'po' => $this->auditDocument(
                type: 'po',
                legacyTable: 'po',
                legacyNumber: 'po_code',
                legacyDate: 'created_date',
                newTable: 'purchase_orders',
                newNumber: 'po_number',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
                fingerprintFn: fn (string $number) => $this->poFingerprint($number),
            ),
            'rr' => $this->auditDocument(
                type: 'rr',
                legacyTable: 'rr',
                legacyNumber: 'rr_code',
                legacyDate: 'created_date',
                newTable: 'receiving_reports',
                newNumber: 'rr_number',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
                fingerprintFn: fn (string $number) => $this->rrFingerprint($number),
            ),
            'sws' => $this->auditDocument(
                type: 'sws',
                legacyTable: 'sws',
                legacyNumber: 'sws_code',
                legacyDate: 'created_date',
                newTable: 'store_withdrawals',
                newNumber: 'sws_number',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
                fingerprintFn: fn (string $number) => $this->swsFingerprint($number),
            ),
            'ts' => $this->auditDocument(
                type: 'ts',
                legacyTable: 'ts',
                legacyNumber: 'ts_code',
                legacyDate: 'created_date',
                newTable: 'transfer_slips',
                newNumber: 'ts_number',
                newDate: 'created_at',
                softDeletes: true,
                since: $since,
                fingerprintFn: fn (string $number) => $this->tsFingerprint($number),
            ),
            'dr' => $this->auditDocument(
                type: 'dr',
                legacyTable: 'dr',
                legacyNumber: 'dr_code',
                legacyDate: 'created_date',
                newTable: 'deliveries',
                newNumber: 'dr_number',
                newDate: 'created_at',
                softDeletes: Schema::hasColumn('deliveries', 'deleted_at'),
                since: $since,
                fingerprintFn: fn (string $number) => $this->drFingerprint($number),
            ),
            'canvassing' => $this->auditCanvassing($since),
            'stock' => [
                'ims_only' => [],
                'new_only' => [],
                'content_mismatches' => [],
                'match_count' => 0,
                'ims_since_count' => 0,
                'new_since_count' => 0,
                'note' => 'See stock_mismatches at report root',
            ],
            default => [
                'ims_only' => [],
                'new_only' => [],
                'content_mismatches' => [],
                'match_count' => 0,
                'ims_since_count' => 0,
                'new_since_count' => 0,
                'error' => "Unknown dataset [{$dataset}]",
            ],
        };
    }

    /**
     * @return list<array{code: string, new_balance: float, ims_balance: float, diff: float}>
     */
    public function auditStock(): array
    {
        $newStocks = DB::table('stock_inventories as si')
            ->join('items as i', 'i.id', '=', 'si.item_id')
            ->when(Schema::hasColumn('stock_inventories', 'is_active'), fn ($q) => $q->where('si.is_active', 1))
            ->select('i.code', DB::raw('SUM(si.balance) as bal'))
            ->groupBy('i.code')
            ->pluck('bal', 'code');

        try {
            $legStocks = $this->legacy()
                ->table('stock_inventory')
                ->where('is_active', 'Y')
                ->select('product_code', DB::raw('SUM(balance) as bal'))
                ->groupBy('product_code')
                ->pluck('bal', 'product_code');
        } catch (Throwable) {
            return [];
        }

        $codes = collect($newStocks->keys())->merge($legStocks->keys())->unique()->sort()->values();
        $mismatches = [];

        foreach ($codes as $code) {
            $new = round((float) ($newStocks[$code] ?? 0), 5);
            $leg = round((float) ($legStocks[$code] ?? 0), 5);
            $diff = round($new - $leg, 5);

            if (abs($diff) <= 0.01) {
                continue;
            }

            $mismatches[] = [
                'code' => (string) $code,
                'new_balance' => $new,
                'ims_balance' => $leg,
                'diff' => $diff,
            ];
        }

        usort($mismatches, fn (array $a, array $b): int => abs($b['diff']) <=> abs($a['diff']));

        return $mismatches;
    }

    private function legacy(): \Illuminate\Database\Connection
    {
        return DB::connection((string) config('reconcile.legacy_connection'));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditByCode(
        string $legacyTable,
        string $legacyCode,
        string $legacyDate,
        string $newTable,
        string $newCode,
        string $newDate,
        bool $softDeletes,
        string $since,
    ): array {
        $legAll = $this->legacy()->table($legacyTable)->pluck($legacyCode)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();
        $legSince = $this->legacy()->table($legacyTable)->where($legacyDate, '>=', $since)->pluck($legacyCode)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();

        $newQuery = DB::table($newTable);
        if ($softDeletes && Schema::hasColumn($newTable, 'deleted_at')) {
            $newQuery->whereNull('deleted_at');
        }
        $newAll = (clone $newQuery)->pluck($newCode)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();
        $newSince = (clone $newQuery)->where($newDate, '>=', $since)->pluck($newCode)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();

        $imsOnly = array_values(array_diff($legSince, $newAll));
        $newOnly = array_values(array_diff($newSince, $legAll));

        return [
            'ims_only' => array_map(fn (string $code) => ['code' => $code], $imsOnly),
            'new_only' => array_map(fn (string $code) => ['code' => $code], $newOnly),
            'content_mismatches' => [],
            'match_count' => count(array_intersect($legSince, $newAll)),
            'ims_since_count' => count($legSince),
            'new_since_count' => count($newSince),
        ];
    }

    /**
     * @param  callable(string): array{ims: string, spfi: string|null}  $fingerprintFn
     * @return array<string, mixed>
     */
    private function auditDocument(
        string $type,
        string $legacyTable,
        string $legacyNumber,
        string $legacyDate,
        string $newTable,
        string $newNumber,
        string $newDate,
        bool $softDeletes,
        string $since,
        callable $fingerprintFn,
    ): array {
        $legAll = $this->legacy()->table($legacyTable)->pluck($legacyNumber)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();
        $legSince = $this->legacy()->table($legacyTable)->where($legacyDate, '>=', $since)->pluck($legacyNumber)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();

        $newQuery = DB::table($newTable);
        if ($softDeletes && Schema::hasColumn($newTable, 'deleted_at')) {
            $newQuery->whereNull('deleted_at');
        }
        $newAll = (clone $newQuery)->pluck($newNumber)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();
        $newSince = (clone $newQuery)->where($newDate, '>=', $since)->pluck($newNumber)->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->filter()->values()->all();

        // Preserve original casing from legacy for import keys
        $legSinceRaw = $this->legacy()->table($legacyTable)->where($legacyDate, '>=', $since)->pluck($legacyNumber)->map(fn ($v) => trim((string) $v))->filter()->values()->all();
        $legSinceByNorm = [];
        foreach ($legSinceRaw as $raw) {
            $legSinceByNorm[DocumentFingerprint::normalizeKey($raw)] = $raw;
        }

        $imsOnly = [];
        foreach ($legSince as $norm) {
            if (! in_array($norm, $newAll, true)) {
                $imsOnly[] = ['number' => $legSinceByNorm[$norm] ?? $norm];
            }
        }

        $newOnly = [];
        $newSinceRaw = (clone $newQuery)->where($newDate, '>=', $since)->pluck($newNumber)->map(fn ($v) => trim((string) $v))->filter()->values()->all();
        foreach ($newSinceRaw as $raw) {
            $norm = DocumentFingerprint::normalizeKey($raw);
            if (! in_array($norm, $legAll, true)) {
                $newOnly[] = ['number' => $raw];
            }
        }

        $overlapCandidates = array_values(array_unique(array_merge(
            array_intersect($legSince, $newAll),
            array_intersect($newSince, $legAll),
        )));

        $mismatches = [];
        $matches = 0;

        foreach ($overlapCandidates as $norm) {
            $number = $legSinceByNorm[$norm] ?? $norm;
            try {
                $fp = $fingerprintFn($number);
            } catch (Throwable $e) {
                $mismatches[] = [
                    'number' => $number,
                    'document_type' => $type,
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            if (($fp['spfi'] ?? null) === null) {
                continue;
            }

            if ($fp['ims'] === $fp['spfi']) {
                $matches++;
            } else {
                $mismatches[] = [
                    'number' => $number,
                    'document_type' => $type,
                    'ims_fingerprint' => $fp['ims'],
                    'spfi_fingerprint' => $fp['spfi'],
                ];
            }
        }

        return [
            'ims_only' => $imsOnly,
            'new_only' => $newOnly,
            'content_mismatches' => $mismatches,
            'match_count' => $matches,
            'ims_since_count' => count($legSince),
            'new_since_count' => count($newSince),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditCanvassing(string $since): array
    {
        try {
            $legSince = $this->legacy()->table('assign_canv_prc')
                ->where('created_date', '>=', $since)
                ->get(['prsnumber', 'productcode', 'supplier_code', 'unit_price', 'is_selected']);
        } catch (Throwable $e) {
            return [
                'ims_only' => [],
                'new_only' => [],
                'content_mismatches' => [],
                'match_count' => 0,
                'ims_since_count' => 0,
                'new_since_count' => 0,
                'error' => $e->getMessage(),
            ];
        }

        $existingKeys = [];
        $rows = DB::table('prs_canvassing_items as pci')
            ->join('prs_items as pi', 'pi.id', '=', 'pci.prs_item_id')
            ->join('prs', 'prs.id', '=', 'pi.prs_id')
            ->join('items as i', 'i.id', '=', 'pi.item_id')
            ->join('suppliers as s', 's.id', '=', 'pci.supplier_id')
            ->get(['prs.prs_number', 'i.code as item_code', 's.code as supplier_code']);

        foreach ($rows as $row) {
            $existingKeys[DocumentFingerprint::normalizeKey($row->prs_number.'|'.$row->item_code.'|'.$row->supplier_code)] = true;
        }

        $imsOnly = [];
        foreach ($legSince as $row) {
            $key = DocumentFingerprint::normalizeKey(trim((string) $row->prsnumber).'|'.trim((string) $row->productcode).'|'.trim((string) $row->supplier_code));
            if (! isset($existingKeys[$key])) {
                $imsOnly[] = [
                    'prsnumber' => trim((string) $row->prsnumber),
                    'productcode' => trim((string) $row->productcode),
                    'supplier_code' => trim((string) $row->supplier_code),
                    'unit_price' => $row->unit_price,
                    'is_selected' => $row->is_selected,
                ];
            }
        }

        $newSinceCount = DB::table('prs_canvassing_items')->where('created_at', '>=', $since)->count();

        return [
            'ims_only' => $imsOnly,
            'new_only' => [],
            'content_mismatches' => [],
            'match_count' => max(0, $legSince->count() - count($imsOnly)),
            'ims_since_count' => $legSince->count(),
            'new_since_count' => $newSinceCount,
        ];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function prsFingerprint(string $number): array
    {
        $legLines = $this->legacy()->table('prs_detail')
            ->where('prsnumber', $number)
            ->get(['productcode', 'qty']);

        $ims = DocumentFingerprint::linesSignature(
            $legLines->map(fn ($r) => ['code' => (string) $r->productcode, 'qty' => $r->qty])->all()
        );

        $prsId = DB::table('prs')->where('prs_number', $number)->whereNull('deleted_at')->value('id');
        if (! $prsId) {
            return ['ims' => DocumentFingerprint::hash($ims), 'spfi' => null];
        }

        $newLines = DB::table('prs_items as pi')
            ->join('items as i', 'i.id', '=', 'pi.item_id')
            ->where('pi.prs_id', $prsId)
            ->get(['i.code', 'pi.quantity']);

        $spfi = DocumentFingerprint::linesSignature(
            $newLines->map(fn ($r) => ['code' => (string) $r->code, 'qty' => $r->quantity])->all()
        );

        return [
            'ims' => DocumentFingerprint::hash($ims),
            'spfi' => DocumentFingerprint::hash($spfi),
        ];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function poFingerprint(string $number): array
    {
        $leg = $this->legacy()->table('po')->where('po_code', $number)->first();
        $legProducts = $this->legacy()->table('po_detail')
            ->where('po_code', $number)
            ->where('is_active', 'Y')
            ->pluck('product_code')
            ->map(fn ($v) => DocumentFingerprint::normalizeKey($v))
            ->sort()
            ->values()
            ->implode(',');

        $ims = DocumentFingerprint::compose([
            'supplier' => $leg->supplier_code ?? '',
            'products' => $legProducts,
        ]);

        $po = DB::table('purchase_orders')->where('po_number', $number)->whereNull('deleted_at')->first();
        if (! $po) {
            return ['ims' => $ims, 'spfi' => null];
        }

        $sup = DB::table('suppliers')->where('id', $po->supplier_id)->value('code');
        $products = DB::table('purchase_order_items as poi')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->where('poi.purchase_order_id', $po->id)
            ->pluck('i.code')
            ->map(fn ($v) => DocumentFingerprint::normalizeKey($v))
            ->sort()
            ->values()
            ->implode(',');

        return [
            'ims' => $ims,
            'spfi' => DocumentFingerprint::compose([
                'supplier' => $sup ?? '',
                'products' => $products,
            ]),
        ];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function rrFingerprint(string $number): array
    {
        $leg = $this->legacy()->table('rr')->where('rr_code', $number)->first();
        $qtyG = (float) $this->legacy()->table('rr_detail')->where('rr_code', $number)->where('is_active', 'Y')->sum('qty_g');
        $products = $this->legacy()->table('rr_detail')->where('rr_code', $number)->where('is_active', 'Y')->pluck('product_code')
            ->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->sort()->values()->implode(',');

        $ims = DocumentFingerprint::compose([
            'po' => $leg->po_code ?? '',
            'qty' => (string) round($qtyG, 5),
            'products' => $products,
        ]);

        $rr = DB::table('receiving_reports')->where('rr_number', $number)->whereNull('deleted_at')->first();
        if (! $rr) {
            return ['ims' => $ims, 'spfi' => null];
        }

        $po = DB::table('purchase_orders')->where('id', $rr->purchase_order_id)->value('po_number');
        $qtyNew = (float) DB::table('receiving_report_items')->where('receiving_report_id', $rr->id)->sum('qty_good');
        $productsNew = DB::table('receiving_report_items as ri')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'ri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->where('ri.receiving_report_id', $rr->id)
            ->pluck('i.code')
            ->map(fn ($v) => DocumentFingerprint::normalizeKey($v))->sort()->values()->implode(',');

        return [
            'ims' => $ims,
            'spfi' => DocumentFingerprint::compose([
                'po' => $po ?? '',
                'qty' => (string) round($qtyNew, 5),
                'products' => $productsNew,
            ]),
        ];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function swsFingerprint(string $number): array
    {
        $legLines = $this->legacy()->table('sws_detail')->where('sws_code', $number)->get(['product_code', 'qty']);
        $ims = DocumentFingerprint::hash(DocumentFingerprint::linesSignature(
            $legLines->map(fn ($r) => ['code' => (string) $r->product_code, 'qty' => $r->qty])->all()
        ));

        $sws = DB::table('store_withdrawals')->where('sws_number', $number)->whereNull('deleted_at')->first();
        if (! $sws) {
            return ['ims' => $ims, 'spfi' => null];
        }

        $newLines = DB::table('store_withdrawal_items as swi')
            ->join('items as i', 'i.id', '=', 'swi.item_id')
            ->where('swi.store_withdrawal_id', $sws->id)
            ->get(['i.code', 'swi.quantity']);

        $spfi = DocumentFingerprint::hash(DocumentFingerprint::linesSignature(
            $newLines->map(fn ($r) => ['code' => (string) $r->code, 'qty' => $r->quantity])->all()
        ));

        return ['ims' => $ims, 'spfi' => $spfi];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function tsFingerprint(string $number): array
    {
        $leg = $this->legacy()->table('ts')->where('ts_code', $number)->first();
        $legQty = (float) $this->legacy()->table('ts_detail')->where('ts_code', $number)->sum('qty');
        $ims = DocumentFingerprint::compose([
            'sws' => DocumentFingerprint::normalizeKey($leg->sws_code ?? ''),
            'qty' => (string) round($legQty, 5),
        ]);

        $ts = DB::table('transfer_slips')->where('ts_number', $number)->whereNull('deleted_at')->first();
        if (! $ts) {
            return ['ims' => $ims, 'spfi' => null];
        }

        $sws = DB::table('store_withdrawals')->where('id', $ts->store_withdrawal_id)->value('sws_number');
        $qty = (float) DB::table('transfer_slip_items')->where('transfer_slip_id', $ts->id)->sum('quantity');

        return [
            'ims' => $ims,
            'spfi' => DocumentFingerprint::compose([
                'sws' => DocumentFingerprint::normalizeKey($sws ?? ''),
                'qty' => (string) round($qty, 5),
            ]),
        ];
    }

    /**
     * @return array{ims: string, spfi: string|null}
     */
    private function drFingerprint(string $number): array
    {
        $legQty = (float) $this->legacy()->table('dr_detail')->where('dr_code', $number)->sum('dr_qty');
        $ims = DocumentFingerprint::compose(['qty' => (string) round($legQty, 5)]);

        $dr = DB::table('deliveries')->where('dr_number', $number);
        if (Schema::hasColumn('deliveries', 'deleted_at')) {
            $dr->whereNull('deleted_at');
        }
        $drRow = $dr->first();
        if (! $drRow) {
            return ['ims' => $ims, 'spfi' => null];
        }

        $qty = 0.0;
        if (Schema::hasTable('delivery_items')) {
            $qty = (float) DB::table('delivery_items')->where('delivery_id', $drRow->id)->sum('quantity');
        }

        return [
            'ims' => $ims,
            'spfi' => DocumentFingerprint::compose(['qty' => (string) round($qty, 5)]),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $stockMismatches
     */
    private function writeCsvReports(string $since, array $results, array $stockMismatches): string
    {
        $dir = storage_path('app/'.trim((string) config('reconcile.report_path'), '/\\').'/'.now()->format('Ymd_His'));
        File::ensureDirectoryExists($dir);

        foreach ($results as $dataset => $payload) {
            foreach (['ims_only', 'new_only', 'content_mismatches'] as $bucket) {
                $rows = $payload[$bucket] ?? [];
                if (! is_array($rows) || $rows === []) {
                    continue;
                }
                $this->writeCsv($dir.'/'.$dataset.'_'.$bucket.'.csv', $rows);
            }
        }

        if ($stockMismatches !== []) {
            $this->writeCsv($dir.'/stock_mismatches.csv', $stockMismatches);
        }

        File::put($dir.'/summary.json', json_encode([
            'since' => $since,
            'generated_at' => now()->toDateTimeString(),
            'datasets' => collect($results)->map(fn (array $r) => [
                'ims_only' => count($r['ims_only'] ?? []),
                'new_only' => count($r['new_only'] ?? []),
                'content_mismatches' => count($r['content_mismatches'] ?? []),
                'match_count' => $r['match_count'] ?? 0,
                'ims_since_count' => $r['ims_since_count'] ?? 0,
                'new_since_count' => $r['new_since_count'] ?? 0,
            ])->all(),
            'stock_mismatch_count' => count($stockMismatches),
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
