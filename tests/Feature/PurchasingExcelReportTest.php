<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->manager = User::query()->create([
        'name' => 'Purchasing Excel Manager',
        'username' => 'purchasing-excel-manager',
        'email' => 'purchasing-excel-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Excel Report Item',
        'code' => 'XLSX-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 25,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-XLSX-001',
        'name' => 'Excel Report Supplier',
        'created_by' => $this->manager->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '71010000888',
        'user_id' => $this->manager->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->subDays(5)->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Excel report PRS',
        'status' => 'APPROVED',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 4,
        'canvasser_id' => $this->manager->id,
        'is_direct_purchase' => false,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-XLSX-001',
        'term_of_payment_type' => 'cash',
        'created_at' => now()->subDay(),
        'total' => 400,
    ]);

    $this->poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'prs_item_id' => $this->prsItem->id,
        'item_id' => $this->item->id,
        'quantity' => 4,
        'unit_price' => 100,
        'total' => 400,
    ]);
});

function assertOpenXmlExcel(TestResponse $response): void
{
    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-type'))->not->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
}

function loadPurchasingExcelSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'pur-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}

it('exports prs not yet po as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.prs-not-yet-po'), [
        'date_to' => now()->toDateString(),
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    expect((string) loadPurchasingExcelSheet($response)->getCell('B8')->getValue())->toBe('71010000888');
});

it('exports prs not yet po as pdf', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.prs-not-yet-po'), [
        'date_to' => now()->toDateString(),
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('exports po not yet delivered as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.po-not-yet-delivered'), [
        'date_to' => now()->toDateString(),
        'po_type' => 'cash',
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    expect(loadPurchasingExcelSheet($response)->getCell('A8')->getValue())->toBe('PO-XLSX-001');
});

it('exports po register per period as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.po-registered-period'), [
        'date_from' => now()->subWeek()->toDateString(),
        'date_to' => now()->endOfDay()->toDateTimeString(),
        'po_type' => 'all',
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    expect(loadPurchasingExcelSheet($response)->getCell('F8')->getValue())->toBe('PO-XLSX-001');
});

it('exports po register per department as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.po-registered-department'), [
        'date_from' => now()->subWeek()->toDateString(),
        'date_to' => now()->endOfDay()->toDateTimeString(),
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    $sheet = loadPurchasingExcelSheet($response);
    expect($sheet->getCell('A8')->getValue())->toContain('Department:');
    expect($sheet->getCell('D9')->getValue())->toBe('PO-XLSX-001');
});

it('exports po register per item as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.po-registered-item'), [
        'as_of' => now()->toDateString(),
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    expect(loadPurchasingExcelSheet($response)->getCell('A8')->getValue())->toBe('XLSX-ITEM-001');
});

it('exports po register per supplier as openxml xlsx', function () {
    $response = $this->actingAs($this->manager)->post(route('procurement.reports.po-registered-supplier'), [
        'as_of' => now()->toDateString(),
        'format' => 'excel',
    ]);

    assertOpenXmlExcel($response);
    expect(loadPurchasingExcelSheet($response)->getCell('A8')->getValue())->toBe('SUP-XLSX-001');
});
