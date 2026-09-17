<?php

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\AccountingInventoryReportService;
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
        'name' => 'Report User',
        'username' => 'report-user-'.uniqid(),
        'email' => 'report-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('accounting-staff');

    $this->category = ItemCategory::query()->create([
        'name' => 'CHEM',
        'code' => 'CHEM-RPT-'.uniqid(),
    ]);

    $this->unit = UnitOfMeasure::query()->create(['name' => 'Kilogram', 'code' => 'KG-RPT-'.uniqid()]);
    $this->item = Item::query()->create([
        'name' => 'Report Chemical',
        'code' => 'CHEM-RPT-ITEM-'.uniqid(),
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'is_active' => true,
    ]);
});

it('builds stock card rows from accounting inventory monthly', function () {
    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR-RPT-001',
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 10,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 10,
            'amount' => 100,
        ],
    ], $this->user);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->not->toBeEmpty();
    expect((float) $rows->first()['qty'])->toBe(10.0);
    expect((float) $rows->first()['unit_cost'])->toBe(10.0);
});

it('reports hasEncodedData when doc_tran rows exist', function () {
    expect(app(AccountingInventoryReportService::class)->hasEncodedData())->toBeFalse();

    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'CV',
        'doc_number' => 'CV-RPT-001',
        'doc_date' => now()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 1,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 5,
            'amount' => 5,
        ],
    ], $this->user);

    expect(app(AccountingInventoryReportService::class)->hasEncodedData())->toBeTrue();
});

it('returns empty stock card rows when no local inventory data exists', function () {
    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->toBeEmpty();
});

it('builds purchase rows from local doc_tran only', function () {
    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR-PUR-001',
        'doc_date' => now()->toDateString(),
        'po_number' => 'PO-PUR-001',
        'party_name' => 'Test Supplier',
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 4,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 12.5,
            'amount' => 50,
        ],
    ], $this->user);

    $rows = app(AccountingInventoryReportService::class)->purchaseRows(
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['rr_number'])->toBe('RR-PUR-001');
    expect((float) $rows->first()['quantity'])->toBe(4.0);
    expect((float) $rows->first()['amount'])->toBe(50.0);
});

it('exports stock card from local tables without legacy fallback', function () {
    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR-EXP-001',
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 2,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 8,
            'amount' => 16,
        ],
    ], $this->user);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.stock-card'), [
            'month' => now()->format('Y-m'),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.ms-excel');
    expect($response->streamedContent())->toContain($this->item->code);
});

it('exports empty stock card when local tables have no matching data', function () {
    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.stock-card'), [
            'month' => now()->format('Y-m'),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.ms-excel');
    expect($response->streamedContent())->not->toContain($this->item->code);
});

it('builds stock card from monthly rows filtered by category_id', function () {
    $itemCode = 'IMP-NO-FK-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-IMP-001',
        'qty' => 10,
        'u_cost' => 7.5,
        'begining' => 2,
        'ending' => 12,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'begining_u_cost' => 7.5,
        'item_id' => null,
        'category_id' => $this->category->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(12.0);
    expect((float) $rows->first()['unit_cost'])->toBe(7.5);
    expect((float) $rows->first()['beginning_amount'])->toBe(15.0);
});

it('builds purchase rows from doc_tran filtered by category_id', function () {
    $factoryCategory = ItemCategory::query()->create([
        'name' => 'FACTORY SUPPLIES',
        'code' => 'FS-RPT-'.uniqid(),
    ]);
    $itemCode = 'IMP-RR-'.uniqid();

    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'RR',
        'doc_no' => 'RR-IMP-PUR-001',
        'doc_date' => now()->toDateString(),
        'po_no' => 'PO-IMP-001',
        'item_code' => $itemCode,
        'qty' => 3,
        'u_cost' => 20,
        'amount' => 60,
        'tran_date' => now()->toDateString(),
        'category' => $factoryCategory->name,
        'party_name' => 'Imported Supplier',
        'item_id' => null,
        'category_id' => $factoryCategory->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->purchaseRows(
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        $factoryCategory->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['rr_number'])->toBe('RR-IMP-PUR-001');
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['quantity'])->toBe(3.0);
    expect((float) $rows->first()['amount'])->toBe(60.0);
});

it('enriches stock card rows when local item code matches', function () {
    AccountingInventoryMonthly::query()->create([
        'item_code' => $this->item->code,
        'doc_code' => 'RR',
        'doc_no' => 'RR-IMP-MATCH-001',
        'qty' => 5,
        'u_cost' => 4,
        'begining' => 0,
        'ending' => 5,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'item_id' => null,
        'category_id' => $this->category->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_description'])->toBe($this->item->name);
    expect($rows->first()['unit'])->toBe($this->unit->name);
});

it('exports document summary with grand total as RR minus TS', function () {
    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'RR',
        'doc_no' => 'RR-SUM-001',
        'doc_date' => now()->toDateString(),
        'item_code' => $this->item->code,
        'qty' => 10,
        'u_cost' => 5,
        'amount' => 100,
        'tran_date' => now()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'TS',
        'doc_no' => 'TS-SUM-001',
        'doc_date' => now()->toDateString(),
        'item_code' => $this->item->code,
        'qty' => -3,
        'u_cost' => 5,
        'amount' => -30,
        'tran_date' => now()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.document-summary'), [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    $content = $response->streamedContent();
    expect($content)->toContain('Grand Total (RR - TS)');
    expect($content)->toContain('70,00');
});

it('filters stock card by PARTS category_id', function () {
    $parts = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-RPT-'.uniqid(),
    ]);
    $itemCode = 'IMP-PARTS-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-PARTS-001',
        'qty' => 8,
        'u_cost' => 11,
        'begining' => 1,
        'ending' => 9,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $parts->name,
        'item_id' => null,
        'category_id' => $parts->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $parts->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(9.0);
});

it('filters stock card by CHEM category_id', function () {
    $itemCode = 'IMP-CHEM-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-CHEM-001',
        'qty' => 6,
        'u_cost' => 3,
        'begining' => 0,
        'ending' => 6,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'item_id' => null,
        'category_id' => $this->category->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(6.0);
});

it('filters stock card by PLASTIC BAG FPL category_id', function () {
    $plastic = ItemCategory::query()->create([
        'name' => 'PLASTIC BAG FPL',
        'code' => 'PBFPL-RPT-'.uniqid(),
    ]);
    $itemCode = 'IMP-PBFPL-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-PBFPL-001',
        'qty' => 4,
        'u_cost' => 2,
        'begining' => 0,
        'ending' => 4,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $plastic->name,
        'item_id' => null,
        'category_id' => $plastic->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $plastic->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(4.0);
});

it('lists report categories from the allow-list only', function () {
    ItemCategory::query()->create([
        'name' => 'FINISHED GOODS',
        'code' => 'FG-RPT-'.uniqid(),
    ]);

    $names = app(AccountingInventoryReportService::class)
        ->reportCategories()
        ->pluck('name')
        ->all();

    expect($names)->toContain('CHEM');
    expect($names)->not->toContain('FINISHED GOODS');
});

it('values stock card beginning with row u_cost like legacy sum register', function () {
    $itemCode = 'BEG-COST-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-BEG-001',
        'qty' => 5,
        'u_cost' => 20,
        'begining' => 10,
        'ending' => 15,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'begining_u_cost' => 8,
        'category_id' => $this->category->id,
    ]);

    $row = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    )->first();

    expect((float) $row['beginning_amount'])->toBe(200.0);
    expect((float) $row['amount'])->toBe(300.0);
    expect((float) $row['transaction'])->toBe(100.0);
});

it('sums stock card beginning and ending across monthly rows for the same cost layer', function () {
    $itemCode = 'BEG-SUM-'.uniqid();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'RR',
        'doc_no' => 'RR-SUM-001',
        'qty' => 5,
        'u_cost' => 10,
        'begining' => 4,
        'ending' => 9,
        'tran_date' => now()->startOfMonth()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
    ]);

    AccountingInventoryMonthly::query()->create([
        'item_code' => $itemCode,
        'doc_code' => 'TS',
        'doc_no' => 'TS-SUM-001',
        'qty' => -2,
        'u_cost' => 10,
        'begining' => 9,
        'ending' => 7,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
    ]);

    $row = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->id,
    )->first();

    expect((float) $row['beginning_qty'])->toBe(13.0);
    expect((float) $row['qty'])->toBe(16.0);
    expect((float) $row['beginning_amount'])->toBe(130.0);
    expect((float) $row['amount'])->toBe(160.0);
});

it('resolves purchase supplier_name from suppliers when party_name is blank', function () {
    $supplier = \App\Models\Supplier::query()->create([
        'name' => 'Joined Supplier Co',
        'code' => 'SUP-RPT-'.uniqid(),
        'created_by' => $this->user->id,
    ]);

    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'RR',
        'doc_no' => 'RR-SUP-001',
        'doc_date' => now()->toDateString(),
        'item_code' => $this->item->code,
        'qty' => 2,
        'u_cost' => 10,
        'amount' => 20,
        'tran_date' => now()->toDateString(),
        'category' => $this->category->name,
        'party_name' => null,
        'supplier_id' => $supplier->id,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    $rows = app(AccountingInventoryReportService::class)->purchaseRows(
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['supplier_name'])->toBe('Joined Supplier Co');
});

it('builds transaction groups per item with document summary', function () {
    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'RR',
        'doc_no' => 'RR-TXN-001',
        'doc_date' => now()->toDateString(),
        'item_code' => $this->item->code,
        'qty' => 10,
        'u_cost' => 5,
        'amount' => 50,
        't_qty' => 10,
        'tran_date' => now()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    AccountingInventoryDocTran::query()->create([
        'doc_code' => 'TS',
        'doc_no' => 'TS-TXN-001',
        'doc_date' => now()->toDateString(),
        'item_code' => $this->item->code,
        'qty' => -4,
        'u_cost' => 5,
        'amount' => -20,
        't_qty' => 6,
        'tran_date' => now()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    $groups = app(AccountingInventoryReportService::class)->transactionGroups(
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        $this->category->id,
    );

    expect($groups)->toHaveCount(1);
    expect($groups->first()['rows'])->toHaveCount(2);
    expect($groups->first()['document_summary']->pluck('doc_type')->all())->toBe(['RR', 'TS']);
    expect((float) $groups->first()['document_summary']->firstWhere('doc_type', 'RR')['qty'])->toBe(10.0);
    expect((float) $groups->first()['document_summary']->firstWhere('doc_type', 'TS')['qty'])->toBe(4.0);
    expect((float) $groups->first()['rr_minus_ts_qty'])->toBe(6.0);
    expect((float) $groups->first()['rr_minus_ts_amount'])->toBe(30.0);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.transaction'), [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    $content = $response->streamedContent();
    expect($content)->toContain('Document summary');
    expect($content)->toContain('Total RR');
    expect($content)->toContain('Total RR - TS');
    expect($content)->toContain('GRAND TOTAL');
    expect($content)->toContain($this->item->code);
});

it('fills restatement percount from IM ending stock and computes variance', function () {
    $document = AccountingInventoryTransaction::make([
        'category_id' => $this->category->id,
        'doc_type' => 'RR',
        'doc_number' => 'RR-REST-001',
        'doc_date' => now()->startOfMonth()->toDateString(),
        'status' => AccountingInventoryTransaction::STATUS_DRAFT,
        'category' => $this->category,
    ]);

    app(AccountingInventoryService::class)->encodeDocument($document, [
        [
            'item_id' => $this->item->id,
            'direction' => AccountingInventoryTransactionLine::DIRECTION_IN,
            'quantity' => 8,
            'unit_of_measure_id' => $this->unit->id,
            'unit_cost' => 4,
            'amount' => 32,
        ],
    ], $this->user);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 10,
        'start_balance' => 0,
        'average_price' => 4,
        'is_active' => true,
        'is_delete' => false,
    ]);

    StockBalance::query()->create([
        'date' => now()->endOfMonth()->toDateString(),
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 0,
        'qty_in1' => 10,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 10,
    ]);

    $rows = app(AccountingInventoryReportService::class)->restatementRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->not->toBeEmpty();
    $row = $rows->firstWhere('item_code', $this->item->code);
    expect($row)->not->toBeNull();
    expect((float) $row['purchase_qty'])->toBe(8.0);
    expect((float) $row['end_theoretical_qty'])->toBe(8.0);
    expect((float) $row['percount_qty'])->toBe(10.0);
    expect((float) $row['percount_amount'])->toBe(40.0);
    expect((float) $row['variance_qty'])->toBe(2.0);
    expect((float) $row['variance_amount'])->toBe(8.0);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.restatement'), [
            'month' => now()->format('Y-m'),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('Beg. Inventory');
    expect($response->streamedContent())->toContain('GRAND TOTAL');
    expect($response->streamedContent())->toContain($this->item->code);
});

it('fills stock card per count percount from IM ending stock', function () {
    AccountingInventoryMonthly::query()->create([
        'item_code' => $this->item->code,
        'doc_code' => 'RR',
        'doc_no' => 'RR-COUNT-001',
        'qty' => 3,
        'u_cost' => 6,
        'begining' => 0,
        'ending' => 3,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'begining_u_cost' => 0,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    StockBalance::query()->create([
        'date' => now()->endOfMonth()->toDateString(),
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 0,
        'qty_in1' => 5,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 5,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardCountRows(
        now()->format('Y-m'),
        $this->category->id,
    );

    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()['stock_card_qty'])->toBe(3.0);
    expect((float) $rows->first()['stock_card_amount'])->toBe(18.0);
    expect((float) $rows->first()['percount_qty'])->toBe(5.0);
    expect((float) $rows->first()['percount_amount'])->toBe(30.0);
    expect((float) $rows->first()['variance_qty'])->toBe(2.0);
    expect((float) $rows->first()['variance_amount'])->toBe(12.0);

    $response = $this->actingAs($this->user)
        ->post(route('accounting.reports.stock-card-count'), [
            'month' => now()->format('Y-m'),
            'category_id' => $this->category->id,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    expect($response->streamedContent())->toContain('Per Stock Card');
    expect($response->streamedContent())->toContain('GRAND TOTAL');
    expect($response->streamedContent())->toContain($this->item->code);
});
