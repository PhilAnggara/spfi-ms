<?php

use App\Models\AccountingInventoryLedger;
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

it('encodes prefilled receiving report into accounting ledger', function () {
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

    $transaction = AccountingInventoryTransaction::query()->first();
    expect($transaction)->not->toBeNull();

    $line = $transaction->lines()->first();
    expect($line)->not->toBeNull();

    $response = $this->actingAs($this->user)
        ->put(route('accounting.inventory-transactions.update', $transaction), [
            'lines' => [
                [
                    'item_id' => $line->item_id,
                    'direction' => $line->direction,
                    'quantity' => 4,
                    'unit_of_measure_id' => $line->unit_of_measure_id,
                    'unit_cost' => 12.5,
                    'amount' => 50,
                    'prefill_quantity' => 4,
                    'prefill_unit_cost' => 12.5,
                ],
            ],
        ]);

    $response->assertRedirect(route('accounting.inventory-transactions.index', ['status' => 'encoded']));

    $transaction->refresh();
    expect($transaction->status)->toBe(AccountingInventoryTransaction::STATUS_ENCODED);

    $ledger = AccountingInventoryLedger::query()
        ->where('accounting_inventory_transaction_id', $transaction->id)
        ->first();

    expect($ledger)->not->toBeNull();
    expect((float) $ledger->balance_qty)->toBe(4.0);
    expect((float) $ledger->weighted_unit_cost)->toBe(12.5);
});

it('creates and encodes manual cv transaction with direction toggle', function () {
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

    $transaction = AccountingInventoryTransaction::query()->where('doc_number', 'CV-TEST-001')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->party_name)->toBeNull();

    $response->assertRedirect(route('accounting.inventory-transactions.transaction', $transaction));

    $this->actingAs($this->user)
        ->put(route('accounting.inventory-transactions.update', $transaction), [
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
        ])
        ->assertRedirect();

    expect($transaction->fresh()->status)->toBe(AccountingInventoryTransaction::STATUS_ENCODED);
    expect(AccountingInventoryTransactionLine::query()->where('direction', 'in')->exists())->toBeTrue();
});

it('blocks encode when outbound quantity exceeds available accounting balance', function () {
    $inbound = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-SEED-001',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $inLine = $inbound->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
        'quantity' => 3,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 10,
        'amount' => 30,
        'sort_order' => 0,
    ]);

    app(\App\Services\Accounting\AccountingInventoryService::class)->encode($inbound, $this->user);

    $outbound = AccountingInventoryTransaction::query()->create([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-SEED-002',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'gl_status' => 'not_required',
        'created_by' => $this->user->id,
    ]);

    $outbound->lines()->create([
        'item_id' => $this->item->id,
        'direction' => AccountingInventoryTransactionLine::DIRECTION_OUT,
        'quantity' => 5,
        'unit_of_measure_id' => $this->unit->id,
        'unit_cost' => 10,
        'amount' => 50,
        'sort_order' => 0,
    ]);

    expect(fn () => app(\App\Services\Accounting\AccountingInventoryService::class)->encode($outbound, $this->user))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('bulk encodes uncorrected draft transactions', function () {
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

    $transactionIds = AccountingInventoryTransaction::query()
        ->whereIn('source_id', [$firstRr->id, $secondRr->id])
        ->pluck('id')
        ->all();

    expect($transactionIds)->toHaveCount(2);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.inventory-transactions.bulk-encode'), [
            'transaction_ids' => $transactionIds,
        ]);

    $response->assertRedirect(route('accounting.inventory-transactions.index', ['status' => 'encoded']));

    expect(AccountingInventoryTransaction::query()
        ->whereIn('id', $transactionIds)
        ->where('status', AccountingInventoryTransaction::STATUS_ENCODED)
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

    $transaction = AccountingInventoryTransaction::query()
        ->where('source_id', $transferSlipId)
        ->where('doc_type', 'TS')
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->lines()->count())->toBe(1);
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
    $response->assertSee('Save Draft');
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
    expect($rrPos)->toBeLessThan($tsPos);
});
