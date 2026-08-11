<?php

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
use Illuminate\Support\Facades\DB;
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
        'name' => 'IM Transaction Report User',
        'username' => 'im-txn-report',
        'email' => 'im-txn-report@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP',
    ]);

    $otherCategory = ItemCategory::query()->create([
        'name' => 'CHEMICAL',
        'code' => 'CH',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Bearing 6205',
        'code' => 'SP-6205',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $otherItem = Item::query()->create([
        'name' => 'Solvent A',
        'code' => 'CH-001',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $otherCategory->id,
        'type' => 'Chemical',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Txn Supplier',
        'code' => 'SUP-TXN-001',
        'created_by' => $this->user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TXN-001',
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 50,
        'unit_price' => 10,
        'line_subtotal' => 500,
        'total' => 500,
    ]);

    $otherPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $otherItem->id,
        'quantity' => 20,
        'unit_price' => 5,
        'line_subtotal' => 100,
        'total' => 100,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-TXN-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-06-10',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 50,
        'qty_bad' => 0,
    ]);

    // Other category — excluded
    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $otherPoItem->id,
        'qty_good' => 20,
        'qty_bad' => 0,
    ]);

    // Out of range RR — excluded
    $rrOut = ReceivingReport::query()->create([
        'rr_number' => 'RR-TXN-OLD',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-05-01',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rrOut->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 99,
        'qty_bad' => 0,
    ]);

    $now = now();

    $swsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-TXN-001',
        'sws_date' => '2026-06-12',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Txn test SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $swsId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 20,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-TXN-001',
        'ts_date' => '2026-06-15',
        'store_withdrawal_id' => $swsId,
        'for_production' => false,
        'remarks' => null,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $tsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 20,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $drId = (int) DB::table('deliveries')->insertGetId([
        'dr_number' => 'DR-TXN-001',
        'dr_date' => '2026-06-20',
        'from_name' => 'IM',
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('delivery_items')->insert([
        'delivery_id' => $drId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'uom' => 'PCS',
        'quantity' => 10,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('exports transaction excel with rr ts and dr lines for the category period', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.transaction'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-type'))->not->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $sheet = loadTransactionSheet($response);

    // Item on the left (merged across its transactions), data on the right
    expect($sheet->getCell('A7')->getValue())->toBe('SP-6205');
    expect($sheet->getCell('B7')->getValue())->toBe('Bearing 6205');

    expect(is_numeric($sheet->getCell('C7')->getValue()))->toBeTrue();
    expect(Date::excelToDateTimeObject($sheet->getCell('C7')->getValue())->format('Y-m-d'))->toBe('2026-06-10');
    expect($sheet->getCell('D7')->getValue())->toBe('RR');
    expect($sheet->getCell('E7')->getValue())->toBe('RR-TXN-001');
    expect((float) $sheet->getCell('F7')->getValue())->toBe(50.0);
    expect($sheet->getCell('G7')->getValue())->toBe('Pieces');

    expect($sheet->getCell('D8')->getValue())->toBe('TS');
    expect($sheet->getCell('E8')->getValue())->toBe('TS-TXN-001');
    expect((float) $sheet->getCell('F8')->getValue())->toBe(20.0);

    expect($sheet->getCell('D9')->getValue())->toBe('DR');
    expect($sheet->getCell('E9')->getValue())->toBe('DR-TXN-001');
    expect((float) $sheet->getCell('F9')->getValue())->toBe(10.0);

    // Header date filters as Excel dates
    expect(is_numeric($sheet->getCell('B3')->getValue()))->toBeTrue();
    expect(Date::excelToDateTimeObject($sheet->getCell('B3')->getValue())->format('Y-m-d'))->toBe('2026-06-01');
    expect(Date::excelToDateTimeObject($sheet->getCell('D3')->getValue())->format('Y-m-d'))->toBe('2026-06-30');

    // Exclusions: other category / out of range
    $docNumbers = [];
    for ($row = 7; $row <= 25; $row++) {
        $doc = $sheet->getCell('E'.$row)->getValue();
        if (is_string($doc) && $doc !== '') {
            $docNumbers[] = $doc;
        }
    }
    expect($docNumbers)->not->toContain('RR-TXN-OLD');
    expect($docNumbers)->toHaveCount(3);

    expect($sheet->getCell('A12')->getValue())->toBe('Prepared by');
    expect($sheet->getCell('A15')->getValue())->toBe('IM Transaction Report User');
    expect($sheet->getCell('D12')->getValue())->toBe('Checked by');
    expect($sheet->getCell('D15')->getValue())->toBe('Daniel Watuna');
    expect($sheet->getCell('F12')->getValue())->toBe('Approved by');
    expect($sheet->getCell('F15')->getValue())->toBe('Rommy Tendean');
});

it('exports transaction pdf successfully', function () {
    $response = $this->actingAs($this->user)->post(route('im.reports.transaction'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'pdf',
    ]);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('orders transaction item groups alphabetically by item name', function () {
    $zebra = Item::query()->create([
        'name' => 'Zebra Gasket',
        'code' => 'A-001',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $apple = Item::query()->create([
        'name' => 'Apple Washer',
        'code' => 'Z-001',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->where('code', 'SUP-TXN-001')->firstOrFail();

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TXN-SORT',
    ]);

    $zebraPoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $zebra->id,
        'quantity' => 5,
        'unit_price' => 1,
        'line_subtotal' => 5,
        'total' => 5,
    ]);

    $applePoItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $apple->id,
        'quantity' => 5,
        'unit_price' => 1,
        'line_subtotal' => 5,
        'total' => 5,
    ]);

    $rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-TXN-SORT',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-06-11',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $zebraPoItem->id,
        'qty_good' => 5,
        'qty_bad' => 0,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $rr->id,
        'purchase_order_item_id' => $applePoItem->id,
        'qty_good' => 5,
        'qty_bad' => 0,
    ]);

    $response = $this->actingAs($this->user)->post(route('im.reports.transaction'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();
    $sheet = loadTransactionSheet($response);

    $itemNames = [];
    for ($row = 7; $row <= 30; $row++) {
        $name = $sheet->getCell('B'.$row)->getValue();
        if (is_string($name) && $name !== '' && ! in_array($name, $itemNames, true)) {
            $itemNames[] = $name;
        }
    }

    expect($itemNames)->toBe(['Apple Washer', 'Bearing 6205', 'Zebra Gasket']);
});

it('does not use rowspan in the transaction pdf markup so page breaks keep columns aligned', function () {
    $groups = collect([
        [
            'item_code' => 'SP-6205',
            'item_name' => 'Bearing 6205',
            'unit' => 'Pieces',
            'rows' => collect([
                ['doc_date' => '2026-06-10', 'doc_type' => 'RR', 'doc_number' => 'RR-TXN-001', 'quantity' => 50.0],
                ['doc_date' => '2026-06-15', 'doc_type' => 'TS', 'doc_number' => 'TS-TXN-001', 'quantity' => 20.0],
                ['doc_date' => '2026-06-20', 'doc_type' => 'DR', 'doc_number' => 'DR-TXN-001', 'quantity' => 10.0],
            ]),
        ],
    ]);

    $html = view('pdf.reports.im-transaction', \App\Support\PdfReport::withDefaults([
        'title' => 'Transaction Report per Category',
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'groups' => $groups,
        'prepared_by_name' => 'IM Transaction Report User',
        'prepared_by_title' => '',
        'checked_by_name' => 'Daniel Watuna',
        'checked_by_title' => 'IM Supervisor',
        'approved_by_name' => 'Rommy Tendean',
        'approved_by_title' => 'IM Manager',
    ]))->render();

    expect($html)->not->toContain('rowspan');
    expect(substr_count($html, '<tr'))->toBeGreaterThanOrEqual(4);
    expect($html)->toContain('RR-TXN-001');
    expect($html)->toContain('TS-TXN-001');
    expect($html)->toContain('DR-TXN-001');
});

it('forbids unrelated roles from exporting transaction report', function () {
    $outsider = User::query()->create([
        'name' => 'Purchasing Outsider',
        'username' => 'purchasing-txn-outsider',
        'email' => 'purchasing-txn-outsider@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $outsider->assignRole('purchasing-staff');

    $this->actingAs($outsider)->post(route('im.reports.transaction'), [
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ])->assertForbidden();
});

function loadTransactionSheet(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-txn-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
