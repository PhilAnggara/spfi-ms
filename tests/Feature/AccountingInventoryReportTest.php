<?php

use App\Models\AccountingInventoryDocTran;
use App\Models\AccountingInventoryMonthly;
use App\Models\AccountingInventoryTransaction;
use App\Models\AccountingInventoryTransactionLine;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
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
        'name' => 'CHEMICAL',
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
        $this->category->name,
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
        $this->category->name,
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
        $this->category->name,
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
            'category' => $this->category->name,
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
            'category' => $this->category->name,
            'format' => 'excel',
        ]);

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.ms-excel');
    expect($response->streamedContent())->not->toContain($this->item->code);
});

it('builds stock card from import monthly rows without item_id or category_id', function () {
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
        'category' => 'SPARE PARTS',
        'item_id' => null,
        'category_id' => null,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        'SPARE PARTS',
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(12.0);
    expect((float) $rows->first()['unit_cost'])->toBe(7.5);
    expect((float) $rows->first()['beginning_amount'])->toBe(15.0);
});

it('builds purchase rows from import doc_tran without item_id or category_id', function () {
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
        'category' => 'FACTORY SUPPLIES',
        'party_name' => 'Imported Supplier',
        'item_id' => null,
        'category_id' => null,
    ]);

    $rows = app(AccountingInventoryReportService::class)->purchaseRows(
        now()->subDay()->toDateString(),
        now()->addDay()->toDateString(),
        'FACTORY SUPPLIES',
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['rr_number'])->toBe('RR-IMP-PUR-001');
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['quantity'])->toBe(3.0);
    expect((float) $rows->first()['amount'])->toBe(60.0);
});

it('enriches import stock card rows when local item code matches', function () {
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
        'category_id' => null,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        $this->category->name,
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_description'])->toBe($this->item->name);
    expect($rows->first()['unit'])->toBe($this->unit->name);
});

it('maps SPARE PARTS filter to stored PARTS category', function () {
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
        'category' => 'PARTS',
        'item_id' => null,
        'category_id' => null,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        'SPARE PARTS',
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(9.0);
});

it('maps CHEMICAL filter to stored CHEM category', function () {
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
        'category' => 'CHEM',
        'item_id' => null,
        'category_id' => null,
    ]);

    $rows = app(AccountingInventoryReportService::class)->stockCardRows(
        now()->format('Y-m'),
        'CHEMICAL',
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['item_code'])->toBe($itemCode);
    expect((float) $rows->first()['qty'])->toBe(6.0);
});
