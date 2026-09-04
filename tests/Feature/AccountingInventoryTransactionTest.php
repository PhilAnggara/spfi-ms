<?php

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\AccountingInventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Accounting',
        'code' => '7200',
        'alias' => 'ACC',
    ]);

    $this->user = User::query()->create([
        'name' => 'Inventory User',
        'username' => 'inventory-user-'.uniqid(),
        'email' => 'inventory-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('accounting-staff');

    $this->category = ItemCategory::query()->create([
        'name' => 'CHEMICAL',
        'code' => 'CHEM-'.uniqid(),
    ]);

    $this->unit = UnitOfMeasure::query()->create(['name' => 'Kilogram', 'code' => 'KG-'.uniqid()]);
    $this->item = Item::query()->create([
        'name' => 'Test Chemical',
        'code' => 'CHEM-ITEM-'.uniqid(),
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);
});

it('lists pending receiving report in accounting inventory queue', function () {
    $supplier = Supplier::query()->create([
        'name' => 'Chem Supplier',
        'code' => 'CHEM-SUP',
        'created_by' => $this->user->id,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-INV-001',
        'supplier_id' => $supplier->id,
        'po_date' => now()->toDateString(),
        'status' => 'APPROVED',
        'created_by' => $this->user->id,
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $this->item->id,
        'quantity' => 100,
        'unit_price' => 10,
        'total' => 1000,
        'line_subtotal' => 1000,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-INV-001',
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.index', [
            'status' => 'pending',
            'category_id' => $this->category->id,
        ]));

    $response->assertSuccessful();
    $response->assertSee('RR-INV-001');
    $response->assertSee('Pending');
});

it('encodes prefilled receiving report into doc_tran', function () {
    $supplier = Supplier::query()->create([
        'name' => 'Chem Supplier',
        'code' => 'CHEM-SUP-2',
        'created_by' => $this->user->id,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-INV-002',
        'supplier_id' => $supplier->id,
        'po_date' => now()->toDateString(),
        'status' => 'APPROVED',
        'created_by' => $this->user->id,
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $this->item->id,
        'quantity' => 100,
        'unit_price' => 12.5,
        'total' => 1250,
        'line_subtotal' => 1250,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-INV-002',
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 4,
    ]);

    $show = $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.show', [
            'docType' => 'rr',
            'id' => $rr->id,
            'category_id' => $this->category->id,
        ]));

    $show->assertSuccessful();
    $show->assertSee($this->item->code);

    $response = $this->actingAs($this->user)
        ->put(route('accounting.inventory-transactions.update', [
            'docType' => 'rr',
            'id' => $rr->id,
            'category_id' => $this->category->id,
        ]), [
            'category_id' => $this->category->id,
            'lines' => [
                [
                    'item_id' => $this->item->id,
                    'direction' => 'in',
                    'quantity' => 4,
                    'unit_of_measure_id' => $this->unit->id,
                    'unit_cost' => 12.5,
                    'amount' => 50,
                    'prefill_quantity' => 4,
                    'prefill_unit_cost' => 12.5,
                ],
            ],
        ]);

    $response->assertRedirect(route('accounting.inventory-transactions.index', ['status' => 'encoded']));

    $row = AccountingInventoryDocTran::query()
        ->where('doc_code', 'RR')
        ->where('doc_no', 'RR-INV-002')
        ->where('category_id', $this->category->id)
        ->first();

    expect($row)->not->toBeNull();
    expect((float) $row->qty)->toBe(4.0);
    expect((float) $row->t_qty)->toBe(4.0);
    expect($row->item_id)->toBe($this->item->id);
    expect($row->source_id)->toBe($rr->id);
    expect($row->supplier_id)->toBe($supplier->id);
    expect($row->purchase_order_id)->toBe($po->id);
});

it('creates and encodes manual cv transaction immediately', function () {
    $response = $this->actingAs($this->user)
        ->post(route('accounting.inventory-transactions.store'), [
            'category_id' => $this->category->id,
            'doc_type' => 'CV',
            'doc_number' => 'CV-TEST-001',
            'doc_date' => now()->toDateString(),
            'remarks' => 'Manual CV',
            'lines' => [
                [
                    'item_id' => $this->item->id,
                    'direction' => 'in',
                    'quantity' => 2,
                    'unit_of_measure_id' => $this->unit->id,
                    'unit_cost' => 5,
                    'amount' => 10,
                ],
            ],
        ]);

    $response->assertRedirect(route('accounting.inventory-transactions.manual', [
        'docType' => 'cv',
        'docNumber' => 'CV-TEST-001',
        'category_id' => $this->category->id,
    ]));

    expect(AccountingInventoryDocTran::query()
        ->where('doc_code', 'CV')
        ->where('doc_no', 'CV-TEST-001')
        ->where('category_id', $this->category->id)
        ->exists())->toBeTrue();
});

it('blocks encode when outbound quantity exceeds available accounting balance', function () {
    $service = app(AccountingInventoryService::class);

    $inbound = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-SEED-001',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    $service->encodeDocument($inbound, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 3,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 10,
            'amount' => 30,
        ],
    ], $this->user);

    $outbound = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-SEED-002',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    expect(fn () => $service->encodeDocument($outbound, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_OUT,
            'quantity' => 5,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 10,
            'amount' => 50,
        ],
    ], $this->user))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('bulk encodes uncorrected source documents', function () {
    $supplier = Supplier::query()->create([
        'name' => 'Bulk Supplier',
        'code' => 'BULK-SUP',
        'created_by' => $this->user->id,
    ]);

    $createRr = function (string $rrNumber, string $poNumber) use ($supplier): ReceivingReport {
        $po = PurchaseOrder::query()->create([
            'po_number' => $poNumber,
            'supplier_id' => $supplier->id,
            'po_date' => now()->toDateString(),
            'status' => 'APPROVED',
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'item_id' => $this->item->id,
            'quantity' => 100,
            'unit_price' => 8,
            'total' => 800,
            'line_subtotal' => 800,
            'discount_amount' => 0,
            'ppn_rate' => 0,
            'ppn_amount' => 0,
            'pph_rate' => 0,
            'pph_amount' => 0,
        ]);

        $rr = ReceivingReport::query()->create([
            'rr_number' => $rrNumber,
            'purchase_order_id' => $po->id,
            'received_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        ReceivingReportItem::query()->create([
            'receiving_report_id' => $rr->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_good' => 2,
        ]);

        return $rr;
    };

    $firstRr = $createRr('RR-BULK-001', 'PO-BULK-001');
    $secondRr = $createRr('RR-BULK-002', 'PO-BULK-002');

    $response = $this->actingAs($this->user)
        ->post(route('accounting.inventory-transactions.bulk-encode'), [
            'documents' => [
                ['doc_type' => 'RR', 'source_id' => $firstRr->id, 'category_id' => $this->category->id],
                ['doc_type' => 'RR', 'source_id' => $secondRr->id, 'category_id' => $this->category->id],
            ],
        ]);

    $response->assertRedirect(route('accounting.inventory-transactions.index', ['status' => 'encoded']));

    expect(AccountingInventoryDocTran::query()
        ->where('doc_code', 'RR')
        ->whereIn('doc_no', ['RR-BULK-001', 'RR-BULK-002'])
        ->where('category_id', $this->category->id)
        ->count())->toBe(2);
});

it('opens transfer slip process screen with prefilled lines', function () {
    $storeWithdrawalId = \Illuminate\Support\Facades\DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TS-INV-001',
        'sws_date' => now()->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'TS inventory test',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $storeWithdrawalItemId = \Illuminate\Support\Facades\DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 5,
        'uom' => $this->unit->name,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $transferSlipId = \Illuminate\Support\Facades\DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-INV-001',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'transfer_to' => 'Production Line A',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 3,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.show', [
            'docType' => 'ts',
            'id' => $transferSlipId,
            'category_id' => $this->category->id,
        ]));

    $response->assertSuccessful();
    $response->assertSee('TS-INV-001');
    $response->assertSee($this->item->code);
    $response->assertSee('Pending');
});

it('returns manual create partial for modal requests', function () {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('accounting.inventory-transactions.create', [
            'modal' => 1,
            'category_id' => $this->category->id,
        ]));

    $response->assertSuccessful();
    $response->assertSee('inv-modal-create-form', false);
    $response->assertDontSee('Back to Queue');
});

it('searches accounting inventory items with optional category filter', function () {
    $otherCategory = ItemCategory::query()->create([
        'name' => 'OTHER',
        'code' => 'OTHER-'.uniqid(),
    ]);

    $otherItem = Item::query()->create([
        'name' => 'Other Item',
        'code' => 'OTHER-ITEM-'.uniqid(),
        'category_id' => $otherCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);

    $searchTerm = substr($this->item->code, 0, 6);

    $filtered = $this->actingAs($this->user)
        ->getJson(route('accounting.inventory-transactions.items.search', [
            'q' => $searchTerm,
            'category_id' => $this->category->id,
        ]));

    $filtered->assertSuccessful();
    expect(collect($filtered->json('items'))->pluck('id'))->toContain($this->item->id);
    expect(collect($filtered->json('items'))->pluck('id'))->not->toContain($otherItem->id);

    $unfiltered = $this->actingAs($this->user)
        ->getJson(route('accounting.inventory-transactions.items.search', [
            'q' => $searchTerm,
        ]));

    $unfiltered->assertSuccessful();
    expect(collect($unfiltered->json('items'))->pluck('id'))->toContain($this->item->id);
});

it('orders inventory queue by document date then display number not document type', function () {
    $sharedDate = '2026-03-15';

    $supplier = Supplier::query()->create([
        'name' => 'Order Supplier',
        'code' => 'ORD-SUP',
        'created_by' => $this->user->id,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-ORDER-001',
        'supplier_id' => $supplier->id,
        'po_date' => $sharedDate,
        'status' => 'APPROVED',
        'created_by' => $this->user->id,
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $this->item->id,
        'quantity' => 10,
        'unit_price' => 5,
        'total' => 50,
        'line_subtotal' => 50,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-ORDER-ZZZ',
        'purchase_order_id' => $po->id,
        'received_date' => $sharedDate,
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 1,
    ]);

    $storeWithdrawalId = \Illuminate\Support\Facades\DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-ORDER-001',
        'sws_date' => $sharedDate,
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Order test',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $storeWithdrawalItemId = \Illuminate\Support\Facades\DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 1,
        'uom' => $this->unit->name,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $transferSlipId = \Illuminate\Support\Facades\DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-ORDER-AAA',
        'ts_date' => $sharedDate,
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'transfer_to' => 'Line B',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 1,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.index', [
            'status' => 'pending',
            'category_id' => $this->category->id,
            'date_from' => $sharedDate,
            'date_to' => $sharedDate,
        ]));

    $response->assertSuccessful();

    $rrPos = strpos($response->getContent(), 'RR-ORDER-ZZZ');
    $tsPos = strpos($response->getContent(), 'TS-ORDER-AAA');

    expect($rrPos)->not->toBeFalse();
    expect($tsPos)->not->toBeFalse();
    expect($tsPos)->toBeLessThan($rrPos);
});

it('returns encode panel partial for modal requests', function () {
    $supplier = Supplier::query()->create([
        'name' => 'Modal Encode Supplier',
        'code' => 'MOD-SUP',
        'created_by' => $this->user->id,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-MODAL-001',
        'supplier_id' => $supplier->id,
        'po_date' => now()->toDateString(),
        'status' => 'APPROVED',
        'created_by' => $this->user->id,
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $this->item->id,
        'quantity' => 10,
        'unit_price' => 6,
        'total' => 60,
        'line_subtotal' => 60,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
    ]);

    $rrLater = ReceivingReport::query()->create([
        'rr_number' => 'RR-MODAL-001',
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rrLater->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 2,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-MODAL-000',
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('accounting.inventory-transactions.show', [
            'docType' => 'rr',
            'id' => $rr->id,
            'category_id' => $this->category->id,
            'modal' => 1,
        ]));

    $response->assertSuccessful();
    $response->assertSee('data-inventory-encode-panel', false);
    $response->assertSee('inventory-encode-form', false);
    $response->assertSee('data-next-document', false);
    $response->assertSee('RR-MODAL-001', false);
    $response->assertDontSee('Back to Queue');
});

it('encodes via json and returns next pending document of same type', function () {
    $supplier = Supplier::query()->create([
        'name' => 'Json Encode Supplier',
        'code' => 'JSON-SUP',
        'created_by' => $this->user->id,
    ]);

    $createRr = function (string $rrNumber, string $poNumber, string $docDate) use ($supplier): ReceivingReport {
        $po = PurchaseOrder::query()->create([
            'po_number' => $poNumber,
            'supplier_id' => $supplier->id,
            'po_date' => $docDate,
            'status' => 'APPROVED',
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
            'unit_price' => 7,
            'total' => 70,
            'line_subtotal' => 70,
            'discount_amount' => 0,
            'ppn_rate' => 0,
            'ppn_amount' => 0,
            'pph_rate' => 0,
            'pph_amount' => 0,
        ]);

        $rr = ReceivingReport::query()->create([
            'rr_number' => $rrNumber,
            'purchase_order_id' => $po->id,
            'received_date' => $docDate,
            'created_by' => $this->user->id,
        ]);

        ReceivingReportItem::query()->create([
            'receiving_report_id' => $rr->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_good' => 2,
        ]);

        return $rr;
    };

    $firstRr = $createRr('RR-JSON-DAY1', 'PO-JSON-DAY1', now()->subDay()->toDateString());
    $createRr('RR-JSON-DAY2', 'PO-JSON-DAY2', now()->toDateString());

    $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.show', [
            'docType' => 'rr',
            'id' => $firstRr->id,
            'category_id' => $this->category->id,
        ]))
        ->assertSuccessful();

    $response = $this->actingAs($this->user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->putJson(route('accounting.inventory-transactions.update', [
            'docType' => 'rr',
            'id' => $firstRr->id,
            'category_id' => $this->category->id,
        ]), [
            'category_id' => $this->category->id,
            'queue_doc_type' => 'RR',
            'queue_category_id' => $this->category->id,
            'lines' => [
                [
                    'item_id' => $this->item->id,
                    'direction' => 'in',
                    'quantity' => 2,
                    'unit_of_measure_id' => $this->unit->id,
                    'unit_cost' => 7,
                    'amount' => 14,
                    'prefill_quantity' => 2,
                    'prefill_unit_cost' => 7,
                ],
            ],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('next.doc_type', 'RR');
    $response->assertJsonPath('next.doc_number', 'RR-JSON-DAY2');
    $response->assertJsonPath('next.category_name', $this->category->name);
    $response->assertJsonPath('next.doc_date_label', now()->format('d M Y'));
    expect($response->json('queue_stats.pending'))->toBeGreaterThanOrEqual(1);
});

it('encodes prefilled transfer slip without requiring prior accounting balance', function () {
    $storeWithdrawalId = \Illuminate\Support\Facades\DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TS-ENC-001',
        'sws_date' => now()->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'TS encode without inbound',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $storeWithdrawalItemId = \Illuminate\Support\Facades\DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 5,
        'uom' => $this->unit->name,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $transferSlipId = \Illuminate\Support\Facades\DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-ENC-001',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'transfer_to' => 'Production Line B',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 3,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('accounting.inventory-transactions.show', [
            'docType' => 'ts',
            'id' => $transferSlipId,
            'category_id' => $this->category->id,
        ]))
        ->assertSuccessful();

    expect(app(AccountingInventoryService::class)->getAvailableQty(
        $this->category->id,
        $this->item->id,
    ))->toBe(0.0);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->putJson(route('accounting.inventory-transactions.update', [
            'docType' => 'ts',
            'id' => $transferSlipId,
            'category_id' => $this->category->id,
        ]), [
            'category_id' => $this->category->id,
            'queue_doc_type' => 'TS',
            'queue_category_id' => $this->category->id,
            'lines' => [
                [
                    'item_id' => $this->item->id,
                    'direction' => 'out',
                    'quantity' => 3,
                    'unit_of_measure_id' => $this->unit->id,
                    'unit_cost' => 0,
                    'amount' => 0,
                    'prefill_quantity' => 3,
                    'prefill_unit_cost' => 0,
                ],
            ],
        ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);

    expect(AccountingInventoryDocTran::query()
        ->where('doc_code', 'TS')
        ->where('doc_no', 'TS-ENC-001')
        ->where('category_id', $this->category->id)
        ->exists())->toBeTrue();
});
