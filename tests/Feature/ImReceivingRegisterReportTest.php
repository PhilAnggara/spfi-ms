<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM RR Register User',
        'username' => 'im-rr-register',
        'email' => 'im-rr-register@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $canvasser = User::query()->create([
        'name' => 'RR Canvasser',
        'username' => 'rr-canvasser',
        'email' => 'rr-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP',
    ]);

    $item = Item::query()->create([
        'name' => 'Bearing 6205',
        'code' => 'SP-6205',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'RR Supplier',
        'code' => 'SUP-RR-REG',
        'created_by' => $this->user->id,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => 'PRS-RR-REG-001',
        'prs_date' => '2026-06-01',
        'date_needed' => '2026-06-10',
        'department_id' => $this->department->id,
        'user_id' => $this->user->id,
        'status' => 'APPROVED',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 50,
        'canvasser_id' => $canvasser->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RR-REG-001',
        'term_of_payment' => '30',
        'term_of_payment_type' => 'Days',
        'created_at' => '2026-06-05 10:00:00',
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $item->id,
        'quantity' => 50,
        'unit_price' => 10,
        'line_subtotal' => 500,
        'total' => 500,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-REG-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-06-15',
        'notes' => 'RR remarks',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 48,
        'qty_bad' => 2,
    ]);

    // Out of range — excluded
    $rrOld = ReceivingReport::query()->create([
        'rr_number' => 'RR-REG-OLD',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-05-01',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rrOld->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 99,
        'qty_bad' => 0,
    ]);
});

it('exports receiving register excel with line columns', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.receiving-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $sheet = loadRegisterSheet($response);

    expect($sheet->getCell('A8')->getValue())->toBe('RR-REG-001');
    expect(Date::excelToDateTimeObject($sheet->getCell('B8')->getValue())->format('Y-m-d'))->toBe('2026-06-15');
    expect($sheet->getCell('C8')->getValue())->toBe('RR Supplier');
    expect($sheet->getCell('D8')->getValue())->toBe('SP-6205');
    expect($sheet->getCell('E8')->getValue())->toBe('Bearing 6205');
    expect($sheet->getCell('F8')->getValue())->toBe('SPARE PARTS');
    expect((float) $sheet->getCell('H8')->getValue())->toBe(48.0);
    expect((float) $sheet->getCell('I8')->getValue())->toBe(2.0);
    expect($sheet->getCell('J8')->getValue())->toBe('PO-RR-REG-001');
    expect($sheet->getCell('L8')->getValue())->toBe('30 / Days');
    expect($sheet->getCell('M8')->getValue())->toBe('RR Canvasser');
    expect($sheet->getCell('N8')->getValue())->toBe('7042');
    expect($sheet->getCell('O8')->getValue())->toBe('RR remarks');
    expect($sheet->getCell('A9')->getValue())->not->toBe('RR-REG-OLD');
});

it('exports receiving register pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.receiving-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('forbids unrelated roles from exporting receiving register', function () {
    $outsider = User::query()->create([
        'name' => 'Outsider',
        'username' => 'rr-reg-outsider',
        'email' => 'rr-reg-outsider@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $outsider->assignRole('purchasing-staff');

    $this->actingAs($outsider)->post(route('im.reports.receiving-register'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'format' => 'excel',
    ])->assertForbidden();
});

function loadRegisterSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-reg-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
