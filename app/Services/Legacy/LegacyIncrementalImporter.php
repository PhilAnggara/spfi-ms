<?php

namespace App\Services\Legacy;

use App\Models\ReceivingReport;
use App\Services\DocumentNumberService;
use App\Services\Reconcile\DocumentFingerprint;
use App\Services\Reconcile\ReconcileDeltaAuditor;
use App\Services\StockService;
use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyDepartmentLookup;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class LegacyIncrementalImporter
{
    use ResolvesLegacyDepartmentLookup;
    use ResolvesLegacyUserLookup;

    /** @var list<int> */
    private array $importedReceivingReportIds = [];

    /** @var list<int> */
    private array $importedTransferSlipIds = [];

    /** @var list<array<string, mixed>> */
    private array $logs = [];

    public function __construct(
        private readonly ReconcileDeltaAuditor $auditor,
        private readonly DocumentNumberService $documentNumbers,
        private readonly StockService $stockService,
    ) {}

    /**
     * @param  list<string>|null  $only
     * @return array{
     *     audit: array<string, mixed>,
     *     imported: array<string, int>,
     *     skipped: array<string, int>,
     *     conflicts_aliased: int,
     *     stock_posted: int,
     *     stock_failed: int,
     *     logs: list<array<string, mixed>>
     * }
     */
    public function apply(string $since, ?array $only = null, string $conflict = 'import-as-alias', bool $applyStock = true, bool $forceStock = true): array
    {
        $this->prepareLegacyUserLookup();
        $this->prepareLegacyDepartmentLookup();
        $this->importedReceivingReportIds = [];
        $this->importedTransferSlipIds = [];
        $this->logs = [];

        $audit = $this->auditor->audit($since, $only, writeCsv: true);
        $datasets = $audit['datasets'];
        $imported = [];
        $skipped = [];
        $aliased = 0;

        $order = ['supplier', 'product', 'prs', 'canvassing', 'po', 'rr', 'sws', 'ts', 'dr'];
        foreach ($order as $dataset) {
            if (! isset($datasets[$dataset])) {
                continue;
            }

            $result = match ($dataset) {
                'supplier' => $this->importSuppliers($datasets['supplier']['ims_only'] ?? []),
                'product' => $this->importProducts($datasets['product']['ims_only'] ?? []),
                'prs' => $this->importDocuments('prs', $datasets['prs'], $conflict),
                'canvassing' => $this->importCanvassing($datasets['canvassing']['ims_only'] ?? []),
                'po' => $this->importDocuments('po', $datasets['po'], $conflict),
                'rr' => $this->importDocuments('rr', $datasets['rr'], $conflict),
                'sws' => $this->importDocuments('sws', $datasets['sws'], $conflict),
                'ts' => $this->importDocuments('ts', $datasets['ts'], $conflict),
                'dr' => $this->importDocuments('dr', $datasets['dr'], $conflict),
                default => ['imported' => 0, 'skipped' => 0, 'aliased' => 0],
            };

            $imported[$dataset] = $result['imported'];
            $skipped[$dataset] = $result['skipped'];
            $aliased += $result['aliased'];
        }

        $stockPosted = 0;
        $stockFailed = 0;
        if ($applyStock) {
            [$stockPosted, $stockFailed] = $this->postStockForImported($forceStock);
        }

        $this->persistLogs();

        return [
            'audit' => $audit,
            'imported' => $imported,
            'skipped' => $skipped,
            'conflicts_aliased' => $aliased,
            'stock_posted' => $stockPosted,
            'stock_failed' => $stockFailed,
            'logs' => $this->logs,
        ];
    }

    /**
     * @param  list<array{code?: string}>  $rows
     * @return array{imported: int, skipped: int, aliased: int}
     */
    private function importSuppliers(array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                $skipped++;

                continue;
            }

            if (DB::table('suppliers')->where('code', $code)->exists()) {
                $skipped++;

                continue;
            }

            $legacy = $this->legacy()->table('supplier')->where('supplier_code', $code)->first();
            if (! $legacy) {
                $skipped++;
                $this->log('supplier', $code, 'skip', null, null, 'legacy row missing');

                continue;
            }

            $id = DB::table('suppliers')->insertGetId([
                'code' => $code,
                'name' => $legacy->supplier_name ?? $code,
                'address' => $legacy->supplier_address ?? null,
                'phone' => $legacy->telephone ?? null,
                'fax' => $legacy->fax ?? null,
                'email' => $legacy->email ?? null,
                'contact_person' => $legacy->contact_person ?? null,
                'created_by' => $this->fallbackUserId(),
                'created_at' => $this->parseDate($legacy->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($legacy->updated_date ?? null) ?? now(),
                'deleted_at' => null,
            ]);

            $this->log('supplier', $code, 'import', $id, $code);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'aliased' => 0];
    }

    /**
     * @param  list<array{code?: string}>  $rows
     * @return array{imported: int, skipped: int, aliased: int}
     */
    private function importProducts(array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $uomByName = $this->buildCaseInsensitiveLookup(
            DB::table('unit_of_measures')->get(['id', 'name', 'code'])
        );
        $categoryByName = $this->buildCaseInsensitiveLookup(
            DB::table('item_categories')->get(['id', 'name', 'code'])
        );

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '' || DB::table('items')->where('code', $code)->exists()) {
                $skipped++;

                continue;
            }

            $legacy = $this->legacy()->table('product')->where('product_code', $code)->first();
            if (! $legacy) {
                $skipped++;

                continue;
            }

            $unitId = $this->resolveMasterId($uomByName, (string) ($legacy->uom_name ?? ''))
                ?? $this->ensureUnitOfMeasure((string) ($legacy->uom_name ?? ''), $uomByName);
            $categoryId = $this->resolveMasterId($categoryByName, (string) ($legacy->product_category ?? ''))
                ?? $this->ensureItemCategory((string) ($legacy->product_category ?? ''), $categoryByName);

            if (! $unitId || ! $categoryId) {
                $this->log('product', $code, 'skip', null, null, 'missing uom/category');
                $skipped++;

                continue;
            }

            $id = DB::table('items')->insertGetId([
                'name' => $legacy->product_name ?? $code,
                'code' => $code,
                'unit_of_measure_id' => $unitId,
                'category_id' => $categoryId,
                'type' => (($legacy->type ?? null) === 'NULL') ? null : ($legacy->type ?? null),
                'stock_on_hand' => 0,
                'is_active' => true,
                'meta' => json_encode((array) $legacy),
                'created_at' => $this->parseDate($legacy->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($legacy->updated_date ?? null) ?? now(),
                'deleted_at' => null,
            ]);

            $this->log('product', $code, 'import', $id, $code);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'aliased' => 0];
    }

    /**
     * @param  array<string, mixed>  $auditSlice
     * @return array{imported: int, skipped: int, aliased: int}
     */
    private function importDocuments(string $type, array $auditSlice, string $conflict): array
    {
        $imported = 0;
        $skipped = 0;
        $aliased = 0;

        foreach ($auditSlice['ims_only'] ?? [] as $row) {
            $number = trim((string) ($row['number'] ?? ''));
            if ($number === '') {
                $skipped++;

                continue;
            }

            $ok = $this->importOneDocument($type, $number, $number, null);
            if ($ok) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        foreach ($auditSlice['content_mismatches'] ?? [] as $row) {
            $imsNumber = trim((string) ($row['number'] ?? ''));
            if ($imsNumber === '') {
                $skipped++;

                continue;
            }

            if ($conflict === 'skip') {
                $this->log($type, $imsNumber, 'conflict_skip', null, null, 'content mismatch skipped');
                $skipped++;

                continue;
            }

            $existingMap = DB::table('reconciliation_number_maps')
                ->where('document_type', $type)
                ->where('ims_number', $imsNumber)
                ->where('resolution', 'import_as_alias')
                ->orderByDesc('id')
                ->first();

            if ($existingMap) {
                $this->log($type, $imsNumber, 'skip', null, $existingMap->spfi_number, 'alias already mapped');
                $skipped++;

                continue;
            }

            $alias = $this->allocateAliasNumber($type, $imsNumber);
            $ok = $this->importOneDocument($type, $imsNumber, $alias, $imsNumber);
            if ($ok) {
                DB::table('reconciliation_number_maps')->insert([
                    'document_type' => $type,
                    'ims_number' => $imsNumber,
                    'spfi_number' => $alias,
                    'existing_spfi_number' => $imsNumber,
                    'resolution' => 'import_as_alias',
                    'ims_fingerprint' => $row['ims_fingerprint'] ?? null,
                    'spfi_fingerprint' => $row['spfi_fingerprint'] ?? null,
                    'meta' => json_encode(['imported_at' => now()->toDateTimeString()]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $imported++;
                $aliased++;
            } else {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'aliased' => $aliased];
    }

    private function importOneDocument(string $type, string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        try {
            return match ($type) {
                'prs' => $this->importPrs($legacyNumber, $spfiNumber, $aliasedFrom),
                'po' => $this->importPo($legacyNumber, $spfiNumber, $aliasedFrom),
                'rr' => $this->importRr($legacyNumber, $spfiNumber, $aliasedFrom),
                'sws' => $this->importSws($legacyNumber, $spfiNumber, $aliasedFrom),
                'ts' => $this->importTs($legacyNumber, $spfiNumber, $aliasedFrom),
                'dr' => $this->importDr($legacyNumber, $spfiNumber, $aliasedFrom),
                default => false,
            };
        } catch (Throwable $e) {
            $this->log($type, $legacyNumber, 'error', null, $spfiNumber, $e->getMessage());

            return false;
        }
    }

    private function importPrs(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $restored = $this->restoreIfSoftDeleted('prs', 'prs_number', $spfiNumber, 'prs', $legacyNumber);
        if ($restored !== null) {
            return $restored;
        }

        if (DB::table('prs')->where('prs_number', $spfiNumber)->exists()) {
            $this->log('prs', $legacyNumber, 'skip', null, $spfiNumber, 'already exists');

            return false;
        }

        $header = $this->legacy()->table('prs')->where('prsnumber', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $departmentId = $this->resolveLegacyDepartmentId($header->department_name ?? null);
        if ($departmentId === null) {
            $this->log('prs', $legacyNumber, 'skip', null, null, 'department not found');

            return false;
        }

        $userId = $this->resolveLegacyUserId($header->createdby ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId();

        $prsId = DB::table('prs')->insertGetId([
            'prs_number' => $spfiNumber,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'prs_date' => $this->parseDate($header->prsdate ?? null) ?? now(),
            'date_needed' => $this->parseDate($header->requestdate ?? null) ?? now(),
            'is_capex' => false,
            'remarks' => $header->remarks ?? null,
            'status' => 'REQUESTED',
            'meta' => json_encode([
                'legacy_prsnumber' => $legacyNumber,
                'aliased_from' => $aliasedFrom,
                'reconcile_import' => true,
            ]),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ]);

        $details = $this->legacy()->table('prs_detail')->where('prsnumber', $legacyNumber)->get();
        foreach ($details as $detail) {
            $itemId = $this->ensureItemId((string) ($detail->productcode ?? ''));
            if (! $itemId) {
                continue;
            }

            DB::table('prs_items')->insert([
                'prs_id' => $prsId,
                'item_id' => $itemId,
                'quantity' => (float) ($detail->qty ?? 0),
                'created_at' => $this->parseDate($detail->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($detail->updated_date ?? null) ?? now(),
            ]);
        }

        $this->log('prs', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $prsId, $spfiNumber);

        return true;
    }

    private function importPo(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $restored = $this->restoreIfSoftDeleted('purchase_orders', 'po_number', $spfiNumber, 'po', $legacyNumber);
        if ($restored !== null) {
            return $restored;
        }

        $header = $this->legacy()->table('po')->where('po_code', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $supplierId = $this->ensureSupplierId((string) ($header->supplier_code ?? ''));
        if (! $supplierId) {
            $this->log('po', $legacyNumber, 'skip', null, null, 'supplier missing');

            return false;
        }

        $currencyId = DB::table('currencies')->where('code', $header->currency ?? '')->value('id')
            ?? DB::table('currencies')->orderBy('id')->value('id');

        $createdBy = $this->resolveLegacyUserId($header->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId();
        $isApproved = strtoupper((string) ($header->is_approved ?? 'N')) === 'Y';
        $isCertified = strtoupper((string) ($header->is_certified ?? 'N')) === 'Y';
        $status = $isApproved ? 'APPROVED' : ($isCertified ? 'PENDING_APPROVAL' : 'DRAFT');

        $discount = (float) ($header->discount ?? 0);
        $ppn = (float) ($header->ppn ?? 0);
        $pph = (float) ($header->pph ?? 0);

        $poId = DB::table('purchase_orders')->insertGetId([
            'supplier_id' => $supplierId,
            'created_by' => $createdBy,
            'status' => $status,
            'po_number' => $spfiNumber,
            'currency_id' => $currencyId,
            'subtotal' => 0,
            'discount_rate' => $discount,
            'discount_amount' => 0,
            'ppn_rate' => $ppn,
            'ppn_amount' => 0,
            'pph_rate' => $pph,
            'pph_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'fees' => 0,
            'total' => 0,
            'remark_type' => 'Normal',
            'remark_text' => $header->remarks ?? null,
            'submitted_at' => $this->parseDate($header->po_date ?? null),
            'approved_at' => $this->parseDate($header->approved_date ?? null),
            'approved_by_user_id' => $this->resolveOptionalUserId($header->approved_by ?? null),
            'certified_by_user_id' => $this->resolveOptionalUserId($header->certified_by ?? null),
            'signature_meta' => json_encode(['legacy_po_code' => $legacyNumber, 'aliased_from' => $aliasedFrom]),
            'meta' => json_encode(['reconcile_import' => true, 'legacy_po_code' => $legacyNumber]),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ]);

        $details = $this->legacy()->table('po_detail')->where('po_code', $legacyNumber)->where('is_active', 'Y')->get();
        $subtotal = 0.0;

        foreach ($details as $detail) {
            $itemId = $this->ensureItemId((string) ($detail->product_code ?? ''));
            if (! $itemId) {
                continue;
            }

            $lineSubtotal = (float) ($detail->sub_total ?? 0);
            $prsNumber = trim((string) ($detail->prsnumber ?? ''));
            $prsItemId = null;
            $qty = 1.0;

            if ($prsNumber !== '') {
                $mappedPrs = $this->resolveMappedNumber('prs', $prsNumber);
                $prsId = DB::table('prs')->where('prs_number', $mappedPrs)->whereNull('deleted_at')->value('id');
                if ($prsId) {
                    $prsItemId = DB::table('prs_items')->where('prs_id', $prsId)->where('item_id', $itemId)->value('id');
                    if ($prsItemId) {
                        $qty = (float) DB::table('prs_items')->where('id', $prsItemId)->value('quantity') ?: 1;
                    }
                }
            }

            $unitPrice = $qty > 0 ? round($lineSubtotal / $qty, 5) : $lineSubtotal;
            $discountAmount = round($lineSubtotal * ($discount / 100), 5);
            $ppnAmount = round(($lineSubtotal - $discountAmount) * ($ppn / 100), 5);
            $pphAmount = round(($lineSubtotal - $discountAmount) * ($pph / 100), 5);
            $total = $lineSubtotal - $discountAmount + $ppnAmount - $pphAmount;
            $subtotal += $lineSubtotal;

            $poiId = DB::table('purchase_order_items')->insert([
                'purchase_order_id' => $poId,
                'item_id' => $itemId,
                'prs_item_id' => $prsItemId,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'discount_rate' => $discount,
                'discount_amount' => $discountAmount,
                'ppn_rate' => $ppn,
                'ppn_amount' => $ppnAmount,
                'pph_rate' => $pph,
                'pph_amount' => $pphAmount,
                'total' => $total,
                'meta' => json_encode(['legacy_prsnumber' => $prsNumber, 'legacy_detail_id' => $detail->id ?? null]),
                'created_at' => $this->parseDate($detail->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($detail->updated_date ?? null) ?? now(),
            ]);

            if ($prsItemId) {
                DB::table('prs_items')->where('id', $prsItemId)->update(['purchase_order_id' => $poId]);
            }
        }

        $discountAmount = round($subtotal * ($discount / 100), 5);
        $ppnAmount = round(($subtotal - $discountAmount) * ($ppn / 100), 5);
        $pphAmount = round(($subtotal - $discountAmount) * ($pph / 100), 5);

        DB::table('purchase_orders')->where('id', $poId)->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'ppn_amount' => $ppnAmount,
            'pph_amount' => $pphAmount,
            'total' => $subtotal - $discountAmount + $ppnAmount - $pphAmount,
        ]);

        $this->log('po', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $poId, $spfiNumber);

        return true;
    }

    private function importRr(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $restored = $this->restoreIfSoftDeleted('receiving_reports', 'rr_number', $spfiNumber, 'rr', $legacyNumber);
        if ($restored !== null) {
            return $restored;
        }

        $header = $this->legacy()->table('rr')->where('rr_code', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $legacyPo = trim((string) ($header->po_code ?? ''));
        $poNumber = $this->resolveMappedNumber('po', $legacyPo);
        $purchaseOrderId = $this->findByNumber('purchase_orders', 'po_number', $poNumber);
        if (! $purchaseOrderId) {
            $purchaseOrderId = $this->ensurePoId($legacyPo);
        }
        if (! $purchaseOrderId) {
            $this->log('rr', $legacyNumber, 'skip', null, null, "PO {$legacyPo} not found");

            return false;
        }

        $requiresBc = strtoupper((string) ($header->Is_BC ?? 'N')) === 'Y';
        $customsTypeId = $requiresBc
            ? DB::table('customs_document_types')->orderBy('id')->value('id')
            : null;

        $rrId = DB::table('receiving_reports')->insertGetId([
            'rr_number' => $spfiNumber,
            'purchase_order_id' => $purchaseOrderId,
            'received_date' => $this->parseDate($header->rr_date ?? null) ?? now(),
            'requires_customs_document' => $requiresBc,
            'customs_document_number' => null,
            'customs_document_type_id' => $customsTypeId,
            'customs_document_date' => null,
            'notes' => $header->rr_remarks ?? null,
            'meta' => json_encode([
                'legacy_rr_code' => $legacyNumber,
                'legacy_po_code' => $legacyPo,
                'aliased_from' => $aliasedFrom,
                'reconcile_import' => true,
            ]),
            'created_by' => $this->resolveLegacyUserId($header->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId(),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ]);

        $details = $this->legacy()->table('rr_detail')->where('rr_code', $legacyNumber)->where('is_active', 'Y')->get();
        $poItems = DB::table('purchase_order_items as poi')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->where('poi.purchase_order_id', $purchaseOrderId)
            ->get(['poi.id', 'poi.item_id', 'i.code', 'poi.unit_price']);

        foreach ($details as $detail) {
            $productCode = DocumentFingerprint::normalizeKey($detail->product_code ?? '');
            $candidate = $poItems->first(fn ($p) => DocumentFingerprint::normalizeKey($p->code) === $productCode);
            if (! $candidate) {
                continue;
            }

            DB::table('receiving_report_items')->insert([
                'receiving_report_id' => $rrId,
                'purchase_order_item_id' => $candidate->id,
                'qty_good' => (float) ($detail->qty_g ?? 0),
                'qty_bad' => (float) ($detail->qty_b ?? 0),
                'meta' => json_encode(['legacy_prs_code' => $detail->prs_code ?? null]),
                'created_at' => $this->parseDate($detail->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($detail->updated_date ?? null) ?? now(),
            ]);
        }

        $this->importedReceivingReportIds[] = $rrId;
        $this->log('rr', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $rrId, $spfiNumber);

        return true;
    }

    private function importSws(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $restored = $this->restoreIfSoftDeleted('store_withdrawals', 'sws_number', $spfiNumber, 'sws', $legacyNumber);
        if ($restored !== null) {
            return $restored;
        }

        $header = $this->legacy()->table('sws')->where('sws_code', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $departmentCode = trim((string) ($header->department_code ?? ''));
        $departmentId = $this->resolveLegacyDepartmentId($departmentCode);
        if ($departmentId === null) {
            $this->log('sws', $legacyNumber, 'skip', null, null, 'department missing');

            return false;
        }

        $rawInfo = trim((string) ($header->sws_info ?? ''));
        $type = Str::contains(Str::lower($rawInfo), 'confirmatory') ? 'confirmatory' : 'normal';
        $info = trim(str_ireplace('CONFIRMATORY;', '', $rawInfo));

        $swsId = DB::table('store_withdrawals')->insertGetId([
            'sws_number' => $spfiNumber,
            'sws_date' => $this->parseDate($header->sws_date ?? null) ?? now(),
            'department_id' => $departmentId,
            'department_code' => $departmentCode !== '' ? $departmentCode : '-',
            'type' => $type,
            'info' => $info !== '' ? $info : null,
            'approved_by' => $this->resolveLegacyUserId($header->approved_by ?? null, $this->fallbackUserId()),
            'approved_at' => $this->parseDate($header->approved_date ?? null),
            'created_by' => $this->resolveLegacyUserId($header->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId(),
            'updated_by' => $this->resolveLegacyUserId($header->updated_by ?? null, $this->fallbackUserId()),
            'meta' => json_encode([
                'legacy_sws_code' => $legacyNumber,
                'aliased_from' => $aliasedFrom,
                'reconcile_import' => true,
            ]),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ]);

        $details = $this->legacy()->table('sws_detail')->where('sws_code', $legacyNumber)->get();
        foreach ($details as $detail) {
            if (strtoupper((string) ($detail->is_active ?? 'Y')) === 'N') {
                continue;
            }
            $itemId = $this->ensureItemId((string) ($detail->product_code ?? ''));
            if (! $itemId) {
                continue;
            }

            $payload = [
                'store_withdrawal_id' => $swsId,
                'item_id' => $itemId,
                'product_code' => $detail->product_code ?? null,
                'quantity' => (float) ($detail->qty ?? 0),
                'stock_on_hand_snapshot' => (float) ($detail->soh ?? 0),
                'uom' => $detail->uom ?? null,
                'created_by' => $this->resolveLegacyUserId($detail->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId(),
                'updated_by' => $this->resolveLegacyUserId($detail->updated_by ?? null, $this->fallbackUserId()),
                'meta' => json_encode(['legacy_detail_id' => $detail->id ?? null]),
                'created_at' => $this->parseDate($detail->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($detail->updated_date ?? null) ?? now(),
                'deleted_at' => null,
            ];

            DB::table('store_withdrawal_items')->insert($payload);
        }

        $this->log('sws', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $swsId, $spfiNumber);

        return true;
    }

    private function importTs(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $restored = $this->restoreIfSoftDeleted('transfer_slips', 'ts_number', $spfiNumber, 'ts', $legacyNumber);
        if ($restored !== null) {
            return $restored;
        }

        $header = $this->legacy()->table('ts')->where('ts_code', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $legacySws = trim((string) ($header->sws_code ?? ''));
        $swsNumber = $this->resolveMappedNumber('sws', $legacySws);
        $swsId = $this->findByNumber('store_withdrawals', 'sws_number', $swsNumber);
        if (! $swsId) {
            $swsId = $this->ensureSwsId($legacySws);
        }
        if (! $swsId) {
            $this->log('ts', $legacyNumber, 'skip', null, null, "SWS {$legacySws} not found");

            return false;
        }

        $blob = strtolower(implode(' ', array_filter([
            (string) ($header->ts_module ?? ''),
            (string) ($header->ts_type ?? ''),
            (string) ($header->ts_to ?? ''),
            (string) ($header->ts_info ?? ''),
        ])));
        $forProduction = str_contains($blob, 'production');

        $tsId = DB::table('transfer_slips')->insertGetId([
            'ts_number' => $spfiNumber,
            'ts_date' => $this->parseDate($header->ts_date ?? null) ?? now(),
            'store_withdrawal_id' => $swsId,
            'for_production' => $forProduction,
            'remarks' => $header->ts_info ?? null,
            'transfer_to' => $header->ts_to ?? null,
            'noted_by' => $this->resolveOptionalUserId($header->noted_by ?? null),
            'noted_at' => $this->parseDate($header->noted_date ?? null),
            'approved_by' => $this->resolveOptionalUserId($header->approved_by ?? null),
            'approved_at' => $this->parseDate($header->approved_date ?? null),
            'received_by' => $this->resolveOptionalUserId($header->received_by ?? null),
            'received_at' => $this->parseDate($header->received_date ?? null),
            'created_by' => $this->resolveLegacyUserId($header->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId(),
            'meta' => json_encode([
                'legacy_ts_code' => $legacyNumber,
                'legacy_sws_code' => $legacySws,
                'aliased_from' => $aliasedFrom,
                'reconcile_import' => true,
            ]),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ]);

        $details = $this->legacy()->table('ts_detail')->where('ts_code', $legacyNumber)->get();
        foreach ($details as $detail) {
            if (strtoupper((string) ($detail->is_active ?? 'Y')) === 'N') {
                continue;
            }
            $itemId = $this->ensureItemId((string) ($detail->product_code ?? ''));
            if (! $itemId) {
                continue;
            }

            $swiId = DB::table('store_withdrawal_items')
                ->where('store_withdrawal_id', $swsId)
                ->where('item_id', $itemId)
                ->value('id');

            DB::table('transfer_slip_items')->insert([
                'transfer_slip_id' => $tsId,
                'store_withdrawal_item_id' => $swiId,
                'item_id' => $itemId,
                'product_code' => $detail->product_code ?? null,
                'quantity' => (float) ($detail->qty ?? 0),
                'meta' => json_encode(['legacy_detail_id' => $detail->id ?? null]),
                'created_at' => $this->parseDate($detail->created_date ?? null) ?? now(),
                'updated_at' => $this->parseDate($detail->updated_date ?? null) ?? now(),
            ]);
        }

        $this->importedTransferSlipIds[] = $tsId;
        $this->log('ts', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $tsId, $spfiNumber);

        return true;
    }

    private function importDr(string $legacyNumber, string $spfiNumber, ?string $aliasedFrom): bool
    {
        $query = DB::table('deliveries')->where('dr_number', $spfiNumber);
        if (Schema::hasColumn('deliveries', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($query->exists()) {
            return false;
        }

        $header = $this->legacy()->table('dr')->where('dr_code', $legacyNumber)->first();
        if (! $header) {
            return false;
        }

        $supplierId = $this->ensureSupplierId((string) ($header->supplier_code ?? ''));

        $payload = [
            'dr_number' => $spfiNumber,
            'supplier_id' => $supplierId,
            'dr_date' => $this->parseDate($header->dr_date ?? null) ?? now(),
            'from_name' => $header->dr_from ?? 'IM - PT. SPFI',
            'from_location' => $header->dr_fromloc ?? null,
            'to_location' => $header->dr_toloc ?? null,
            'remarks' => $header->dr_remarks ?? null,
            'or_number' => $header->or_code ?? null,
            'dm_number' => $header->dm_code ?? null,
            'created_by' => $this->resolveLegacyUserId($header->created_by ?? null, $this->fallbackUserId()) ?? $this->fallbackUserId(),
            'meta' => json_encode(['legacy_dr_code' => $legacyNumber, 'aliased_from' => $aliasedFrom, 'reconcile_import' => true]),
            'created_at' => $this->parseDate($header->created_date ?? null) ?? now(),
            'updated_at' => $this->parseDate($header->updated_date ?? null) ?? now(),
            'deleted_at' => null,
        ];

        $payload = array_filter(
            $payload,
            fn ($key) => Schema::hasColumn('deliveries', $key),
            ARRAY_FILTER_USE_KEY
        );

        $drId = DB::table('deliveries')->insertGetId($payload);

        if (Schema::hasTable('delivery_items')) {
            $details = $this->legacy()->table('dr_detail')->where('dr_code', $legacyNumber)->get();
            foreach ($details as $detail) {
                $itemId = $this->ensureItemId((string) ($detail->product_code ?? ''));
                $line = [
                    'delivery_id' => $drId,
                    'item_id' => $itemId,
                    'product_code' => $detail->product_code ?? null,
                    'quantity' => (float) ($detail->dr_qty ?? 0),
                    'uom' => $detail->dr_uom ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $line = array_filter($line, fn ($key) => Schema::hasColumn('delivery_items', $key), ARRAY_FILTER_USE_KEY);
                DB::table('delivery_items')->insert($line);
            }
        }

        $this->log('dr', $legacyNumber, $aliasedFrom ? 'import_alias' : 'import', $drId, $spfiNumber);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, aliased: int}
     */
    private function importCanvassing(array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $prsNumber = $this->resolveMappedNumber('prs', trim((string) ($row['prsnumber'] ?? '')));
            $productCode = trim((string) ($row['productcode'] ?? ''));
            $supplierCode = trim((string) ($row['supplier_code'] ?? ''));

            $prsId = DB::table('prs')->where('prs_number', $prsNumber)->whereNull('deleted_at')->value('id');
            $itemId = $this->ensureItemId($productCode);
            $supplierId = $this->ensureSupplierId($supplierCode);

            if (! $prsId || ! $itemId || ! $supplierId) {
                $skipped++;

                continue;
            }

            $prsItemId = DB::table('prs_items')->where('prs_id', $prsId)->where('item_id', $itemId)->value('id');
            if (! $prsItemId) {
                $skipped++;

                continue;
            }

            if (DB::table('prs_canvassing_items')->where('prs_item_id', $prsItemId)->where('supplier_id', $supplierId)->exists()) {
                $skipped++;

                continue;
            }

            $isSelected = strtoupper((string) ($row['is_selected'] ?? 'N')) === 'Y';
            $id = DB::table('prs_canvassing_items')->insertGetId([
                'prs_id' => $prsId,
                'prs_item_id' => $prsItemId,
                'supplier_id' => $supplierId,
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'is_selected' => $isSelected,
                'term_of_payment_type' => 'credit',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($isSelected) {
                DB::table('prs_items')->where('id', $prsItemId)->update([
                    'selected_canvassing_item_id' => $id,
                ]);
            }

            $this->log('canvassing', "{$prsNumber}|{$productCode}|{$supplierCode}", 'import', $id, null);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'aliased' => 0];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function postStockForImported(bool $force): array
    {
        $posted = 0;
        $failed = 0;

        foreach ($this->importedReceivingReportIds as $rrId) {
            try {
                $rr = ReceivingReport::query()->with('items.purchaseOrderItem.item')->find($rrId);
                if (! $rr) {
                    continue;
                }

                $lines = [];
                foreach ($rr->items as $item) {
                    $poi = $item->purchaseOrderItem;
                    if (! $poi || ! $poi->item) {
                        continue;
                    }
                    $lines[(int) $item->purchase_order_item_id] = [
                        'purchase_order_item_id' => (int) $item->purchase_order_item_id,
                        'item_id' => (int) $poi->item_id,
                        'product_code' => (string) $poi->item->code,
                        'qty_good' => (float) $item->qty_good,
                        'unit_price' => (float) ($poi->unit_price ?? 0),
                    ];
                }

                if ($lines === []) {
                    continue;
                }

                $this->stockService->applyReceivingReportAdjustment(
                    receivingReport: $rr,
                    currentLines: $lines,
                    previousLines: [],
                    userId: null,
                    allowNegativeBalance: $force,
                );
                $posted += count($lines);
            } catch (Throwable $e) {
                $failed++;
                $this->log('stock_rr', (string) $rrId, 'error', $rrId, null, $e->getMessage());
            }
        }

        foreach ($this->importedTransferSlipIds as $tsId) {
            try {
                $ts = DB::table('transfer_slips')->where('id', $tsId)->first();
                if (! $ts) {
                    continue;
                }

                $items = DB::table('transfer_slip_items as ti')
                    ->join('items as i', 'i.id', '=', 'ti.item_id')
                    ->where('ti.transfer_slip_id', $tsId)
                    ->get(['ti.id', 'ti.item_id', 'ti.quantity', 'i.code']);

                $lines = [];
                foreach ($items as $item) {
                    $lines[] = [
                        'item_id' => (int) $item->item_id,
                        'product_code' => (string) $item->code,
                        'quantity' => (float) $item->quantity,
                        'reference_line_id' => (int) $item->id,
                    ];
                }

                if ($lines === []) {
                    continue;
                }

                $date = DocumentFingerprint::normalizeDate($ts->ts_date) ?: now()->toDateString();
                $this->stockService->applyTransferSlipIssue(
                    transferSlipId: $tsId,
                    movementDate: $date,
                    lines: $lines,
                    userId: null,
                    allowNegativeBalance: $force,
                );
                $posted += count($lines);
            } catch (Throwable $e) {
                $failed++;
                $this->log('stock_ts', (string) $tsId, 'error', $tsId, null, $e->getMessage());
            }
        }

        return [$posted, $failed];
    }

    private function allocateAliasNumber(string $type, string $imsNumber): string
    {
        return match (strtoupper($type)) {
            'PO' => $this->documentNumbers->previewNext('PO'),
            'RR' => $this->documentNumbers->previewNext('RR'),
            'TS' => $this->documentNumbers->previewNext('TS'),
            'DR' => $this->documentNumbers->previewNext('DR'),
            'PRS' => $this->nextDeptSequenceNumber('prs', 'prs_number', $imsNumber),
            'SWS' => $this->nextDeptSequenceNumber('store_withdrawals', 'sws_number', $imsNumber),
            default => $imsNumber.'-IMS',
        };
    }

    private function nextDeptSequenceNumber(string $table, string $column, string $imsNumber): string
    {
        $prefix = preg_replace('/\d+$/', '', $imsNumber) ?: $imsNumber;
        $rows = DB::table($table)->where($column, 'like', $prefix.'%')->pluck($column);
        $max = 0;
        foreach ($rows as $number) {
            $suffix = substr((string) $number, strlen($prefix));
            if (ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        do {
            $max++;
            $candidate = $prefix.str_pad((string) $max, 7, '0', STR_PAD_LEFT);
        } while (DB::table($table)->where($column, $candidate)->exists());

        return $candidate;
    }

    private function resolveMappedNumber(string $type, string $imsNumber): string
    {
        if ($imsNumber === '') {
            return $imsNumber;
        }

        $mapped = DB::table('reconciliation_number_maps')
            ->where('document_type', $type)
            ->where('ims_number', $imsNumber)
            ->orderByDesc('id')
            ->value('spfi_number');

        if ($mapped) {
            return (string) $mapped;
        }

        $norm = DocumentFingerprint::normalizeKey($imsNumber);
        $rows = DB::table('reconciliation_number_maps')
            ->where('document_type', $type)
            ->orderByDesc('id')
            ->get(['ims_number', 'spfi_number']);

        foreach ($rows as $row) {
            if (DocumentFingerprint::normalizeKey($row->ims_number) === $norm) {
                return (string) $row->spfi_number;
            }
        }

        return $imsNumber;
    }

    private function findByNumber(string $table, string $column, string $number, bool $includeTrashed = false): ?int
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $query = DB::table($table)->where($column, $number);
        if (! $includeTrashed && Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $id = $query->value('id');
        if ($id) {
            return (int) $id;
        }

        $norm = DocumentFingerprint::normalizeKey($number);
        $loose = DB::table($table);
        if (! $includeTrashed && Schema::hasColumn($table, 'deleted_at')) {
            $loose->whereNull('deleted_at');
        }

        $id = $loose->whereRaw('UPPER('.$column.') = ?', [$norm])->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Soft-deleted rows still occupy unique document numbers. Restore them when IMS still has the doc.
     *
     * @return bool|null true=restored, false=active exists (skip insert), null=no row
     */
    private function restoreIfSoftDeleted(string $table, string $column, string $spfiNumber, string $dataset, string $legacyKey): ?bool
    {
        $id = $this->findByNumber($table, $column, $spfiNumber, includeTrashed: true);
        if (! $id) {
            return null;
        }

        if (! Schema::hasColumn($table, 'deleted_at')) {
            $this->log($dataset, $legacyKey, 'skip', $id, $spfiNumber, 'already exists');

            return false;
        }

        $deletedAt = DB::table($table)->where('id', $id)->value('deleted_at');
        if ($deletedAt === null) {
            $this->log($dataset, $legacyKey, 'skip', $id, $spfiNumber, 'already exists');

            return false;
        }

        DB::table($table)->where('id', $id)->update([
            'deleted_at' => null,
            'updated_at' => now(),
        ]);

        // Also restore soft-deleted child lines when present.
        $childMap = [
            'purchase_orders' => ['purchase_order_items', 'purchase_order_id'],
            'receiving_reports' => ['receiving_report_items', 'receiving_report_id'],
            'store_withdrawals' => ['store_withdrawal_items', 'store_withdrawal_id'],
            'transfer_slips' => ['transfer_slip_items', 'transfer_slip_id'],
            'prs' => ['prs_items', 'prs_id'],
        ];
        if (isset($childMap[$table])) {
            [$childTable, $fk] = $childMap[$table];
            if (Schema::hasTable($childTable) && Schema::hasColumn($childTable, 'deleted_at')) {
                DB::table($childTable)->where($fk, $id)->whereNotNull('deleted_at')->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            }
        }

        if ($dataset === 'rr') {
            $this->importedReceivingReportIds[] = $id;
        }
        if ($dataset === 'ts') {
            $this->importedTransferSlipIds[] = $id;
        }

        $this->log($dataset, $legacyKey, 'restore', $id, $spfiNumber, 'restored soft-deleted document from IMS sync');

        return true;
    }

    private function ensurePoId(string $legacyPoNumber): ?int
    {
        $legacyPoNumber = trim($legacyPoNumber);
        if ($legacyPoNumber === '') {
            return null;
        }

        $existing = $this->findByNumber('purchase_orders', 'po_number', $this->resolveMappedNumber('po', $legacyPoNumber));
        if ($existing) {
            return $existing;
        }

        if (! $this->legacy()->table('po')->where('po_code', $legacyPoNumber)->exists()) {
            return null;
        }

        if ($this->importPo($legacyPoNumber, $legacyPoNumber, null)) {
            return $this->findByNumber('purchase_orders', 'po_number', $legacyPoNumber);
        }

        return null;
    }

    private function ensureSwsId(string $legacySwsNumber): ?int
    {
        $legacySwsNumber = trim($legacySwsNumber);
        if ($legacySwsNumber === '') {
            return null;
        }

        $existing = $this->findByNumber('store_withdrawals', 'sws_number', $this->resolveMappedNumber('sws', $legacySwsNumber));
        if ($existing) {
            return $existing;
        }

        // Case-insensitive IMS lookup
        $legacyRow = $this->legacy()->table('sws')->where('sws_code', $legacySwsNumber)->first();
        if (! $legacyRow) {
            $legacyRow = $this->legacy()->table('sws')
                ->whereRaw('UPPER(sws_code) = ?', [DocumentFingerprint::normalizeKey($legacySwsNumber)])
                ->first();
        }
        if (! $legacyRow) {
            return null;
        }

        $actualCode = trim((string) $legacyRow->sws_code);
        if ($this->importSws($actualCode, $actualCode, null)) {
            return $this->findByNumber('store_withdrawals', 'sws_number', $actualCode);
        }

        return $this->findByNumber('store_withdrawals', 'sws_number', $actualCode);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, int>
     */
    private function buildCaseInsensitiveLookup($rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            foreach (['name', 'code'] as $field) {
                $value = trim((string) ($row->{$field} ?? ''));
                if ($value === '') {
                    continue;
                }
                $key = DocumentFingerprint::normalizeKey($value);
                if (! isset($map[$key])) {
                    $map[$key] = (int) $row->id;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $lookup
     */
    private function resolveMasterId(array $lookup, string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        return $lookup[DocumentFingerprint::normalizeKey($raw)] ?? null;
    }

    /**
     * @param  array<string, int>  $lookup
     */
    private function ensureUnitOfMeasure(string $name, array &$lookup): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = $this->resolveMasterId($lookup, $name);
        if ($existing) {
            return $existing;
        }

        $code = Str::upper(Str::limit(preg_replace('/\s+/', '', $name) ?: $name, 20, ''));
        if (DB::table('unit_of_measures')->where('code', $code)->exists()) {
            $code = $code.'_'.Str::lower(Str::random(3));
        }

        $id = (int) DB::table('unit_of_measures')->insertGetId([
            'name' => $name,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        $lookup[DocumentFingerprint::normalizeKey($name)] = $id;
        $lookup[DocumentFingerprint::normalizeKey($code)] = $id;
        $this->log('uom', $name, 'import', $id, $code);

        return $id;
    }

    /**
     * @param  array<string, int>  $lookup
     */
    private function ensureItemCategory(string $name, array &$lookup): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $existing = $this->resolveMasterId($lookup, $name);
        if ($existing) {
            return $existing;
        }

        $code = Str::upper(Str::limit(preg_replace('/\s+/', '', $name) ?: $name, 20, ''));
        if (DB::table('item_categories')->where('code', $code)->exists()) {
            $code = $code.'_'.Str::lower(Str::random(3));
        }

        $id = (int) DB::table('item_categories')->insertGetId([
            'name' => $name,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        $lookup[DocumentFingerprint::normalizeKey($name)] = $id;
        $lookup[DocumentFingerprint::normalizeKey($code)] = $id;
        $this->log('item_category', $name, 'import', $id, $code);

        return $id;
    }

    private function resolveOptionalUserId(mixed $raw): ?int
    {
        return $this->resolveLegacyUserId($raw, $this->fallbackUserId(), true, true);
    }

    private function ensureItemId(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $id = DB::table('items')->where('code', $code)->value('id');
        if ($id) {
            return (int) $id;
        }

        // Import dependency product even if created before cutoff
        $result = $this->importProducts([['code' => $code]]);

        return $result['imported'] > 0
            ? (int) DB::table('items')->where('code', $code)->value('id')
            : null;
    }

    private function ensureSupplierId(string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $id = DB::table('suppliers')->where('code', $code)->value('id');
        if ($id) {
            return (int) $id;
        }

        $result = $this->importSuppliers([['code' => $code]]);

        return $result['imported'] > 0
            ? (int) DB::table('suppliers')->where('code', $code)->value('id')
            : null;
    }

    private function legacy(): \Illuminate\Database\Connection
    {
        return DB::connection((string) config('reconcile.legacy_connection'));
    }

    private function fallbackUserId(): int
    {
        return $this->resolveLegacyFallbackUserId(2);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '' || strtoupper(trim((string) $value)) === 'NULL') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function log(string $dataset, string $legacyKey, string $action, ?int $newId, ?string $spfiNumber, ?string $message = null): void
    {
        $this->logs[] = [
            'dataset' => $dataset,
            'legacy_key' => $legacyKey,
            'action' => $action,
            'new_id' => $newId,
            'spfi_number' => $spfiNumber,
            'status' => str_starts_with($action, 'error') || $action === 'skip' || $action === 'conflict_skip' ? $action : 'imported',
            'message' => $message,
            'meta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function persistLogs(): void
    {
        if ($this->logs === [] || ! Schema::hasTable('reconciliation_import_logs')) {
            return;
        }

        foreach (array_chunk($this->logs, 200) as $chunk) {
            DB::table('reconciliation_import_logs')->insert($chunk);
        }
    }
}
