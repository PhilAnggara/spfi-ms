<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\Reconcile\ImsNumberAlignAuditor;
use App\Services\Reconcile\ImsNumberAligner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    config([
        'reconcile.legacy_connection' => config('database.default'),
        'reconcile.default_since' => '2026-07-01',
    ]);

    createLegacyAlignTables();

    $department = Department::query()->create([
        'name' => 'Inventory',
        'code' => '7046',
        'alias' => 'IM',
    ]);

    $user = User::query()->create([
        'name' => 'Align User',
        'username' => 'align-user',
        'email' => 'align-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-ALI',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Alignment Parts',
        'code' => 'ALN',
    ]);

    Item::query()->create([
        'name' => 'Align Item',
        'code' => 'ALIGN-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    Supplier::query()->create([
        'name' => 'Align Supplier',
        'code' => 'SUP-ALIGN',
        'created_by' => $user->id,
    ]);
});

it('audits TS fingerprint matches even when the number differs', function () {
    $date = '2026-07-10 08:00:00';

    seedLegacySws('SWS-IMS-001', $date, 'ALIGN-001', 5);
    seedLegacyTs('TS-IMS-001', 'SWS-IMS-001', $date, 'ALIGN-001', 5);

    $swsId = createStoreWithdrawal('SWS-IMS-001', '2026-07-10');
    createTransferSlip('TS-SPFI-001', $swsId, '2026-07-10', 'ALIGN-001', 5);

    $audit = app(ImsNumberAlignAuditor::class)->audit('2026-07-01', ['ts'], writeCsv: false);
    $actions = collect($audit['datasets']['ts']['actions']);

    expect($actions->firstWhere('ims_number', 'TS-IMS-001')['action'])->toBe('rename_to_ims');
});

it('marks TS fingerprint collisions across multiple IMS numbers as manual review', function () {
    $date = '2026-07-12 08:00:00';

    seedLegacySws('SWS-IMS-AMB', $date, 'ALIGN-001', 5);
    seedLegacyTs('TS-IMS-A', 'SWS-IMS-AMB', $date, 'ALIGN-001', 5);
    seedLegacyTs('TS-IMS-B', 'SWS-IMS-AMB', $date, 'ALIGN-001', 5);

    $swsId = createStoreWithdrawal('SWS-IMS-AMB', '2026-07-12');
    createTransferSlip('TS-SPFI-AMB', $swsId, '2026-07-12', 'ALIGN-001', 5);

    $audit = app(ImsNumberAlignAuditor::class)->audit('2026-07-01', ['ts'], writeCsv: false);
    $actions = collect($audit['datasets']['ts']['actions']);

    expect($actions->firstWhere('ims_number', 'TS-IMS-A')['action'])->toBe('manual_review');
    expect($actions->firstWhere('ims_number', 'TS-IMS-B')['action'])->toBe('manual_review');
});

it('audits RR fingerprint matches even when the number differs', function () {
    $date = '2026-07-13 08:00:00';

    seedLegacyPo('PO-IMS-001', $date, 'ALIGN-001');
    seedLegacyRr('RR-IMS-001', 'PO-IMS-001', $date, 'ALIGN-001', 4);

    $poId = createPurchaseOrder('PO-IMS-001');
    createReceivingReport('RR-SPFI-001', $poId, '2026-07-13', 'ALIGN-001', 4);

    $audit = app(ImsNumberAlignAuditor::class)->audit('2026-07-01', ['rr'], writeCsv: false);
    $actions = collect($audit['datasets']['rr']['actions']);

    expect($actions->firstWhere('ims_number', 'RR-IMS-001')['action'])->toBe('rename_to_ims');
});

it('retires orphan reconcile alias when the IMS TS number already matches', function () {
    $date = '2026-07-15 08:00:00';

    seedLegacySws('SWS-IMS-ORPHAN', $date, 'ALIGN-001', 5);
    seedLegacyTs('019323', 'SWS-IMS-ORPHAN', $date, 'ALIGN-001', 5);

    $swsId = createStoreWithdrawal('SWS-IMS-ORPHAN', '2026-07-15');
    $canonicalId = createTransferSlip('019323', $swsId, '2026-07-15', 'ALIGN-001', 5, [
        'legacy_ts_code' => '019323',
        'legacy_sws_code' => 'SWS-IMS-ORPHAN',
        'aliased_from' => null,
        'reconcile_import' => true,
    ]);
    $orphanId = createTransferSlip('011051', $swsId, '2026-07-15', 'ALIGN-001', 5, [
        'legacy_ts_code' => '019323',
        'legacy_sws_code' => 'SWS-IMS-ORPHAN',
        'aliased_from' => '019323',
        'reconcile_import' => true,
    ]);

    DB::table('reconciliation_number_maps')->insert([
        'document_type' => 'ts',
        'ims_number' => '019323',
        'spfi_number' => '011051',
        'existing_spfi_number' => '019323',
        'resolution' => 'import_as_alias',
        'ims_fingerprint' => 'ims',
        'spfi_fingerprint' => 'spfi',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $audit = app(ImsNumberAlignAuditor::class)->audit('2026-07-01', ['ts'], writeCsv: false);
    $actions = collect($audit['datasets']['ts']['actions']);
    $orphanAction = $actions->firstWhere('action', 'retire_orphan_alias');

    expect($orphanAction)->not->toBeNull();
    expect($orphanAction['ims_number'])->toBe('019323');
    expect($orphanAction['spfi_id'])->toBe($canonicalId);
    expect($orphanAction['retire_spfi_id'])->toBe($orphanId);

    $result = app(ImsNumberAligner::class)->apply('2026-07-01', ['ts']);

    expect($result['applied']['ts'])->toBeGreaterThanOrEqual(1);
    expect(DB::table('transfer_slips')->where('id', $canonicalId)->value('ts_number'))->toBe('019323');
    expect(DB::table('transfer_slips')->where('id', $canonicalId)->value('deleted_at'))->toBeNull();
    expect(DB::table('transfer_slips')->where('id', $orphanId)->value('ts_number'))->toBe('DELETED-'.$orphanId);
    expect(DB::table('transfer_slips')->where('id', $orphanId)->value('deleted_at'))->not->toBeNull();
    expect(DB::table('transfer_slips')->whereNull('deleted_at')->where('ts_number', '011051')->exists())->toBeFalse();

    expect(DB::table('reconciliation_number_maps')
        ->where('document_type', 'ts')
        ->where('ims_number', '019323')
        ->where('spfi_number', '011051')
        ->where('resolution', 'superseded')
        ->exists())->toBeTrue();

    expect(DB::table('reconciliation_number_maps')
        ->where('document_type', 'ts')
        ->where('ims_number', '019323')
        ->where('spfi_number', '019323')
        ->where('resolution', 'ims_canonical')
        ->exists())->toBeTrue();
});

it('aligner promotes alias TS to the IMS number and releases the wrong SPFI number', function () {
    $date = '2026-07-11 08:00:00';

    seedLegacySws('SWS-IMS-002', $date, 'ALIGN-001', 7);
    seedLegacyTs('TS-IMS-002', 'SWS-IMS-002', $date, 'ALIGN-001', 7);

    $swsId = createStoreWithdrawal('SWS-IMS-002', '2026-07-11');
    $keptId = createTransferSlip('TS-IMS-002', $swsId, '2026-07-11', 'ALIGN-001', 9);
    $aliasId = createTransferSlip('011222', $swsId, '2026-07-11', 'ALIGN-001', 7, [
        'legacy_ts_code' => 'TS-IMS-002',
        'legacy_sws_code' => 'SWS-IMS-002',
        'aliased_from' => 'TS-IMS-002',
        'reconcile_import' => true,
    ]);

    DB::table('reconciliation_number_maps')->insert([
        'document_type' => 'ts',
        'ims_number' => 'TS-IMS-002',
        'spfi_number' => '011222',
        'existing_spfi_number' => 'TS-IMS-002',
        'resolution' => 'import_as_alias',
        'ims_fingerprint' => 'ims',
        'spfi_fingerprint' => 'spfi',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(ImsNumberAligner::class)->apply('2026-07-01', ['ts']);

    expect($result['applied']['ts'])->toBe(1);
    expect($result['skipped']['ts'])->toBeGreaterThanOrEqual(0);

    expect(DB::table('transfer_slips')->where('id', $aliasId)->value('ts_number'))->toBe('TS-IMS-002');
    expect(DB::table('transfer_slips')->where('id', $keptId)->value('ts_number'))->toBe('DELETED-'.$keptId);
    expect(DB::table('transfer_slips')->where('id', $keptId)->value('deleted_at'))->not->toBeNull();

    expect(DB::table('reconciliation_number_maps')
        ->where('document_type', 'ts')
        ->where('ims_number', 'TS-IMS-002')
        ->where('spfi_number', 'TS-IMS-002')
        ->where('resolution', 'ims_canonical')
        ->exists())->toBeTrue();
});

it('aligner releases an occupied IMS number before direct TS rename', function () {
    $date = '2026-07-14 08:00:00';

    seedLegacySws('SWS-IMS-004', $date, 'ALIGN-001', 6);
    seedLegacyTs('TS-IMS-004', 'SWS-IMS-004', $date, 'ALIGN-001', 6);

    $swsId = createStoreWithdrawal('SWS-IMS-004', '2026-07-14');
    $occupiedId = createTransferSlip('TS-IMS-004', $swsId, '2026-07-14', 'ALIGN-001', 9);
    $canonicalId = createTransferSlip('TS-SPFI-004', $swsId, '2026-07-14', 'ALIGN-001', 6);

    $result = app(ImsNumberAligner::class)->apply('2026-07-01', ['ts']);

    expect($result['applied']['ts'])->toBe(1);
    expect(DB::table('transfer_slips')->where('id', $canonicalId)->value('ts_number'))->toBe('TS-IMS-004');
    expect(DB::table('transfer_slips')->where('id', $occupiedId)->value('ts_number'))->toBe('DELETED-'.$occupiedId);
});

it('previewNext ignores reconcile alias document numbers', function () {
    $swsId = createStoreWithdrawal('SWS-IMS-003', '2026-07-12');
    createTransferSlip('047100', $swsId, '2026-07-12', 'ALIGN-001', 1, null, '2026-07-12 08:00:00');
    createTransferSlip('011101', $swsId, '2026-07-12', 'ALIGN-001', 1, [
        'legacy_ts_code' => '047100',
        'legacy_sws_code' => 'SWS-IMS-003',
        'aliased_from' => '047100',
        'reconcile_import' => true,
    ], '2026-07-12 09:00:00');

    DB::table('reconciliation_number_maps')->insert([
        'document_type' => 'ts',
        'ims_number' => '047100',
        'spfi_number' => '011101',
        'existing_spfi_number' => '047100',
        'resolution' => 'import_as_alias',
        'ims_fingerprint' => 'ims',
        'spfi_fingerprint' => 'spfi',
        'meta' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(DocumentNumberService::class)->previewNext('TS'))->toBe('047101');
});

it('runs align report mode via mocked auditor', function () {
    mock(ImsNumberAlignAuditor::class, function ($mock): void {
        $mock->shouldReceive('audit')
            ->once()
            ->withArgs(function (string $since, $only, bool $writeCsv): bool {
                return $since === '2026-07-01' && $only === ['ts'] && $writeCsv === true;
            })
            ->andReturn([
                'since' => '2026-07-01',
                'generated_at' => now()->toDateTimeString(),
                'datasets' => [
                    'ts' => [
                        'actions' => [],
                        'action_counts' => ['rename_to_ims' => 1],
                        'ims_since_count' => 1,
                        'spfi_since_count' => 1,
                    ],
                ],
                'report_dir' => storage_path('app/reconcile-reports/align-test'),
            ]);
    });

    $this->artisan('reconcile:align-ims-numbers', [
        '--report' => true,
        '--since' => '2026-07-01',
        '--only' => 'ts',
    ])
        ->expectsOutputToContain('IMS canonical number alignment')
        ->expectsOutputToContain('CSV reports')
        ->assertSuccessful();
});

function createLegacyAlignTables(): void
{
    if (! Schema::hasTable('sws')) {
        Schema::create('sws', function (Blueprint $table): void {
            $table->id();
            $table->string('sws_code')->nullable();
            $table->string('department_code')->nullable();
            $table->string('sws_info')->nullable();
            $table->timestamp('sws_date')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_date')->nullable();
        });
    }

    if (! Schema::hasTable('sws_detail')) {
        Schema::create('sws_detail', function (Blueprint $table): void {
            $table->id();
            $table->string('sws_code')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('qty', 15, 5)->default(0);
            $table->decimal('soh', 15, 5)->default(0);
            $table->string('uom')->nullable();
            $table->string('is_active')->default('Y');
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    if (! Schema::hasTable('ts')) {
        Schema::create('ts', function (Blueprint $table): void {
            $table->id();
            $table->string('ts_code')->nullable();
            $table->string('sws_code')->nullable();
            $table->string('ts_module')->nullable();
            $table->string('ts_type')->nullable();
            $table->string('ts_to')->nullable();
            $table->string('ts_info')->nullable();
            $table->timestamp('ts_date')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('noted_by')->nullable();
            $table->timestamp('noted_date')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_date')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamp('received_date')->nullable();
        });
    }

    if (! Schema::hasTable('ts_detail')) {
        Schema::create('ts_detail', function (Blueprint $table): void {
            $table->id();
            $table->string('ts_code')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('qty', 15, 5)->default(0);
            $table->string('is_active')->default('Y');
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }

    if (! Schema::hasTable('po')) {
        Schema::create('po', function (Blueprint $table): void {
            $table->id();
            $table->string('po_code')->nullable();
            $table->string('supplier_code')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }

    if (! Schema::hasTable('po_detail')) {
        Schema::create('po_detail', function (Blueprint $table): void {
            $table->id();
            $table->string('po_code')->nullable();
            $table->string('product_code')->nullable();
            $table->string('is_active')->default('Y');
            $table->decimal('sub_total', 15, 5)->default(0);
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }

    if (! Schema::hasTable('rr')) {
        Schema::create('rr', function (Blueprint $table): void {
            $table->id();
            $table->string('rr_code')->nullable();
            $table->string('po_code')->nullable();
            $table->string('rr_remarks')->nullable();
            $table->string('created_by')->nullable();
            $table->string('Is_BC')->nullable();
            $table->timestamp('rr_date')->nullable();
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }

    if (! Schema::hasTable('rr_detail')) {
        Schema::create('rr_detail', function (Blueprint $table): void {
            $table->id();
            $table->string('rr_code')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('qty_g', 15, 5)->default(0);
            $table->decimal('qty_b', 15, 5)->default(0);
            $table->string('is_active')->default('Y');
            $table->timestamp('created_date')->nullable();
            $table->timestamp('updated_date')->nullable();
        });
    }
}

function seedLegacyPo(string $number, string $createdDate, string $productCode): void
{
    DB::table('po')->insert([
        'po_code' => $number,
        'supplier_code' => 'SUP-ALIGN',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
    ]);

    DB::table('po_detail')->insert([
        'po_code' => $number,
        'product_code' => $productCode,
        'is_active' => 'Y',
        'sub_total' => 100,
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
    ]);
}

function seedLegacyRr(string $number, string $poNumber, string $createdDate, string $productCode, float $qtyGood): void
{
    DB::table('rr')->insert([
        'rr_code' => $number,
        'po_code' => $poNumber,
        'rr_remarks' => 'Legacy RR',
        'created_by' => 'tester',
        'Is_BC' => 'N',
        'rr_date' => '2026-07-13',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
    ]);

    DB::table('rr_detail')->insert([
        'rr_code' => $number,
        'product_code' => $productCode,
        'qty_g' => $qtyGood,
        'qty_b' => 0,
        'is_active' => 'Y',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
    ]);
}

function seedLegacySws(string $number, string $createdDate, string $productCode, float $quantity): void
{
    DB::table('sws')->insert([
        'sws_code' => $number,
        'department_code' => '7046',
        'sws_info' => 'Legacy SWS',
        'sws_date' => '2026-07-10',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
        'created_by' => 'tester',
        'updated_by' => 'tester',
        'approved_by' => 'tester',
        'approved_date' => $createdDate,
    ]);

    DB::table('sws_detail')->insert([
        'sws_code' => $number,
        'product_code' => $productCode,
        'qty' => $quantity,
        'soh' => 100,
        'uom' => 'PCS',
        'is_active' => 'Y',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
        'created_by' => 'tester',
        'updated_by' => 'tester',
    ]);
}

function seedLegacyTs(string $number, string $swsNumber, string $createdDate, string $productCode, float $quantity): void
{
    DB::table('ts')->insert([
        'ts_code' => $number,
        'sws_code' => $swsNumber,
        'ts_module' => 'warehouse',
        'ts_type' => 'regular',
        'ts_to' => 'Production',
        'ts_info' => 'Legacy TS',
        'ts_date' => '2026-07-10',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
        'created_by' => 'tester',
        'noted_by' => 'tester',
        'noted_date' => $createdDate,
        'approved_by' => 'tester',
        'approved_date' => $createdDate,
        'received_by' => 'tester',
        'received_date' => $createdDate,
    ]);

    DB::table('ts_detail')->insert([
        'ts_code' => $number,
        'product_code' => $productCode,
        'qty' => $quantity,
        'is_active' => 'Y',
        'created_date' => $createdDate,
        'updated_date' => $createdDate,
    ]);
}

function createStoreWithdrawal(string $number, string $date): int
{
    return (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => $number,
        'sws_date' => $date,
        'department_id' => Department::query()->firstOrFail()->id,
        'department_code' => '7046',
        'type' => 'regular',
        'info' => 'SWS for alignment',
        'created_by' => User::query()->value('id'),
        'updated_by' => User::query()->value('id'),
        'created_at' => $date.' 08:00:00',
        'updated_at' => $date.' 08:00:00',
        'deleted_at' => null,
    ]);
}

function createPurchaseOrder(string $number): int
{
    $poId = (int) DB::table('purchase_orders')->insertGetId([
        'supplier_id' => Supplier::query()->where('code', 'SUP-ALIGN')->firstOrFail()->id,
        'created_by' => User::query()->value('id'),
        'status' => 'APPROVED',
        'po_number' => $number,
        'created_at' => '2026-07-13 08:00:00',
        'updated_at' => '2026-07-13 08:00:00',
        'deleted_at' => null,
    ]);

    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $poId,
        'item_id' => Item::query()->where('code', 'ALIGN-001')->firstOrFail()->id,
        'quantity' => 4,
        'unit_price' => 25,
        'line_subtotal' => 100,
        'total' => 100,
        'created_at' => '2026-07-13 08:00:00',
        'updated_at' => '2026-07-13 08:00:00',
    ]);

    return $poId;
}

function createReceivingReport(string $number, int $purchaseOrderId, string $date, string $productCode, float $qtyGood): int
{
    $rrId = (int) DB::table('receiving_reports')->insertGetId([
        'rr_number' => $number,
        'purchase_order_id' => $purchaseOrderId,
        'received_date' => $date,
        'created_by' => User::query()->value('id'),
        'created_at' => $date.' 08:00:00',
        'updated_at' => $date.' 08:00:00',
        'deleted_at' => null,
    ]);

    $poiId = (int) DB::table('purchase_order_items')
        ->where('purchase_order_id', $purchaseOrderId)
        ->where('item_id', Item::query()->where('code', $productCode)->firstOrFail()->id)
        ->value('id');

    DB::table('receiving_report_items')->insert([
        'receiving_report_id' => $rrId,
        'purchase_order_item_id' => $poiId,
        'qty_good' => $qtyGood,
        'qty_bad' => 0,
        'created_at' => $date.' 08:00:00',
        'updated_at' => $date.' 08:00:00',
        'deleted_at' => null,
    ]);

    return $rrId;
}

function createTransferSlip(
    string $number,
    int $storeWithdrawalId,
    string $date,
    string $productCode,
    float $quantity,
    ?array $meta = null,
    ?string $createdAt = null,
): int {
    $itemId = Item::query()->where('code', $productCode)->firstOrFail()->id;
    $swsItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $itemId,
        'product_code' => $productCode,
        'quantity' => $quantity,
        'uom' => 'PCS',
        'created_by' => User::query()->value('id'),
        'updated_by' => User::query()->value('id'),
        'created_at' => $createdAt ?? $date.' 08:00:00',
        'updated_at' => $createdAt ?? $date.' 08:00:00',
        'deleted_at' => null,
    ]);

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => $number,
        'ts_date' => $date,
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'remarks' => 'TS for alignment',
        'created_by' => User::query()->value('id'),
        'meta' => $meta ? json_encode($meta) : null,
        'created_at' => $createdAt ?? $date.' 08:00:00',
        'updated_at' => $createdAt ?? $date.' 08:00:00',
        'deleted_at' => null,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $itemId,
        'product_code' => $productCode,
        'quantity' => $quantity,
        'created_by' => User::query()->value('id'),
        'updated_by' => User::query()->value('id'),
        'created_at' => $createdAt ?? $date.' 08:00:00',
        'updated_at' => $createdAt ?? $date.' 08:00:00',
        'deleted_at' => null,
    ]);

    return $transferSlipId;
}
