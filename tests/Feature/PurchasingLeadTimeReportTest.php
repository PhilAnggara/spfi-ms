<?php

use App\Exports\PurchasingLeadTimeSpreadsheet;
use App\Http\Controllers\PurchasingReportController;
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
use App\Support\PdfFormatters;
use App\Support\PdfReport;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Shared\Date;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->manager = User::query()->create([
        'name' => 'Purchasing Manager',
        'username' => 'purchasing-manager-lead-time',
        'email' => 'purchasing-manager-lead-time@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->canvasser = User::query()->create([
        'name' => 'Lead Time Canvasser',
        'username' => 'lead-time-canvasser',
        'email' => 'lead-time-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $item = Item::query()->create([
        'name' => 'Lead Time Test Item',
        'code' => 'LT-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);
    $this->item = $item;

    $supplier = Supplier::query()->create([
        'code' => 'SUP-LT-001',
        'name' => 'Lead Time Supplier',
        'created_by' => $this->manager->id,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => '71010000999',
        'user_id' => $this->manager->id,
        'department_id' => $department->id,
        'prs_date' => now()->subDays(20)->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Lead time report PRS',
        'status' => 'COMPLETED',
    ]);

    $assignedAt = now()->subDays(10)->startOfDay();

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => $assignedAt,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-LT-001',
        'created_at' => now()->subDays(5),
    ]);

    $purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'unit_price' => 1000,
        'total' => 5000,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-LT-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->subDays(3)->toDateString(),
        'created_by' => $this->manager->id,
    ]);

    \Illuminate\Support\Facades\DB::table('receiving_reports')
        ->where('id', $receivingReport->id)
        ->update(['created_at' => now()->subDays(3)->startOfDay()]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'qty_good' => 5,
        'qty_bad' => 0,
    ]);

    $this->reportPayload = [
        'date_from' => now()->subMonth()->toDateString(),
        'date_to' => now()->toDateString(),
        'format' => 'pdf',
    ];
});

it('generates purchasing lead time report as pdf', function () {
    $request = Request::create(
        route('procurement.reports.purchasing-lead-time'),
        'POST',
        $this->reportPayload,
    );
    $request->setUserResolver(fn () => $this->manager);

    $response = app(PurchasingReportController::class)->purchasingLeadTime($request);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('generates purchasing lead time report as excel', function () {
    $request = Request::create(
        route('procurement.reports.purchasing-lead-time'),
        'POST',
        [
            ...$this->reportPayload,
            'format' => 'excel',
        ],
    );
    $request->setUserResolver(fn () => $this->manager);

    $response = app(PurchasingReportController::class)->purchasingLeadTime($request);

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
});

it('calculates lead time days from assigned canvasser date to receiving report date', function () {
    $controller = app(\App\Http\Controllers\PurchasingReportController::class);
    $method = new ReflectionMethod($controller, 'buildPurchasingLeadTimeRows');
    $method->setAccessible(true);

    $rows = $method->invoke(
        $controller,
        now()->subMonth()->startOfDay(),
        now()->endOfDay(),
        null
    );

    expect($rows)->toHaveCount(1);
    expect($rows->first()['lead_time_days'])->toBe(7);
});

it('formats table dates with dashed month abbreviation', function () {
    expect(PdfFormatters::tableDate('2026-01-15'))->toBe('15-Jan-2026');
    expect(PdfFormatters::tableDate(null))->toBe('');
});

it('renders dashed table dates while keeping header period format', function () {
    $controller = app(\App\Http\Controllers\PurchasingReportController::class);
    $method = new ReflectionMethod($controller, 'buildPurchasingLeadTimeRows');
    $method->setAccessible(true);

    $dateFrom = now()->subMonth()->startOfDay();
    $dateTo = now()->endOfDay();

    $rows = $method->invoke($controller, $dateFrom, $dateTo, null);
    $firstRow = $rows->first();

    $html = view('pdf.reports.purchasing-lead-time', array_merge(
        PdfReport::withDefaults([
            'title' => 'Purchasing Lead Time Report',
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'canvasser' => 'All',
            'rows' => $rows,
        ]),
        [
            'fmtDate' => fn (mixed $value) => PdfFormatters::date($value),
            'fmtMoney' => fn (float|int|string $value) => PdfFormatters::money($value),
            'fmtQty' => fn (float|int|string $value) => PdfFormatters::qty($value),
        ],
    ))->render();

    expect($html)->toContain(PdfFormatters::tableDate($firstRow['prs_date']));
    expect($html)->toContain(PdfFormatters::tableDate($firstRow['assigned_canvasser_at']));
    expect($html)->toContain(PdfFormatters::tableDate($firstRow['po_date']));
    expect($html)->toContain(PdfFormatters::tableDate($firstRow['rr_date']));
    expect($html)->toContain(PdfFormatters::date($dateFrom->toDateString()));
    expect($html)->not->toContain(PdfFormatters::date($firstRow['prs_date']));
});

it('writes native excel date values for table date columns', function () {
    $controller = app(PurchasingReportController::class);
    $method = new ReflectionMethod($controller, 'buildPurchasingLeadTimeRows');
    $method->setAccessible(true);

    $dateFrom = now()->subMonth()->startOfDay();
    $dateTo = now()->endOfDay();
    $rows = $method->invoke($controller, $dateFrom, $dateTo, null);
    $firstRow = $rows->first();

    $export = new PurchasingLeadTimeSpreadsheet([
        'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
        'title' => 'Purchasing Lead Time Report',
        'date_from' => $dateFrom->toDateString(),
        'date_to' => $dateTo->toDateString(),
        'canvasser' => 'All',
        'printed_at' => now()->format('d M Y H:i'),
        'rows' => $rows,
    ]);

    $buildMethod = new ReflectionMethod($export, 'build');
    $buildMethod->setAccessible(true);
    $sheet = $buildMethod->invoke($export)->getActiveSheet();

    $dateColumns = [
        'C' => 'prs_date',
        'H' => 'assigned_canvasser_at',
        'J' => 'po_date',
        'M' => 'rr_date',
    ];

    foreach ($dateColumns as $column => $field) {
        $cell = $sheet->getCell($column.'10');

        expect(is_numeric($cell->getValue()))->toBeTrue();

        $excelDate = Date::excelToDateTimeObject($cell->getValue());
        expect($excelDate->format('Y-m-d'))->toBe(
            \Carbon\Carbon::parse($firstRow[$field])->startOfDay()->format('Y-m-d')
        );

        expect($sheet->getStyle($column.'10')->getNumberFormat()->getFormatCode())
            ->toBe('dd-mmm-yyyy');
    }
});

it('filters the po not yet delivered report by purchase order payment type without JSON queries', function () {
    $supplier = Supplier::query()->create([
        'code' => 'SUP-PO-RPT-001',
        'name' => 'PO Report Supplier',
        'created_by' => $this->manager->id,
    ]);

    $cashOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-CASH-001',
        'term_of_payment_type' => 'cash',
        'created_at' => now()->subDays(2),
    ]);

    $creditOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-CREDIT-001',
        'term_of_payment_type' => 'credit',
        'created_at' => now()->subDay(),
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $cashOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
        'unit_price' => 100,
        'total' => 200,
        'meta' => ['term_of_payment_type' => 'cash'],
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $creditOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'unit_price' => 150,
        'total' => 450,
        'meta' => ['term_of_payment_type' => 'credit'],
    ]);

    $request = Request::create(
        route('procurement.reports.po-not-yet-delivered'),
        'POST',
        [
            'date_to' => now()->toDateString(),
            'po_type' => 'cash',
            'format' => 'excel',
        ],
    );
    $request->setUserResolver(fn () => $this->manager);

    $response = app(PurchasingReportController::class)->poNotYetDelivered($request);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(200);
    expect($response->getStatusCode())->toBeLessThan(300);
    expect($response->headers->get('content-type'))->toContain(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    expect($response->headers->get('content-type'))->not->toContain('application/vnd.ms-excel');
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $tmp = tempnam(sys_get_temp_dir(), 'po-nyd-xlsx');
    file_put_contents($tmp, $content);

    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }

    expect($sheet->getCell('A8')->getValue())->toBe('PO-CASH-001');
    expect($sheet->getCell('C8')->getValue())->toBe('CASH');

    $highestRow = $sheet->getHighestDataRow();
    $poNumbers = [];
    for ($row = 8; $row <= $highestRow; $row++) {
        $poNumbers[] = (string) $sheet->getCell('A'.$row)->getValue();
    }

    expect($poNumbers)->not->toContain('PO-CREDIT-001');
});

it('excludes fully received purchase orders from the po not yet delivered report', function () {
    $supplier = Supplier::query()->create([
        'code' => 'SUP-PO-NYD-001',
        'name' => 'PO NYD Supplier',
        'created_by' => $this->manager->id,
    ]);

    $receivedOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RECEIVED-001',
        'term_of_payment_type' => 'cash',
        'created_at' => now()->subDays(2),
    ]);

    $pendingOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-PENDING-001',
        'term_of_payment_type' => 'cash',
        'created_at' => now()->subDay(),
    ]);

    $receivedItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $receivedOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 4,
        'unit_price' => 100,
        'total' => 400,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $pendingOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'unit_price' => 100,
        'total' => 300,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-NYD-001',
        'purchase_order_id' => $receivedOrder->id,
        'received_date' => now()->subDay()->toDateString(),
        'created_by' => $this->manager->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $receivedItem->id,
        'qty_good' => 4,
        'qty_bad' => 0,
    ]);

    $request = Request::create(
        route('procurement.reports.po-not-yet-delivered'),
        'POST',
        [
            'date_to' => now()->toDateString(),
            'po_type' => 'cash',
            'format' => 'excel',
        ],
    );
    $request->setUserResolver(fn () => $this->manager);

    $response = app(PurchasingReportController::class)->poNotYetDelivered($request);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    $tmp = tempnam(sys_get_temp_dir(), 'po-nyd-xlsx');
    file_put_contents($tmp, $content);

    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }

    $highestRow = $sheet->getHighestDataRow();
    $poNumbers = [];
    for ($row = 8; $row <= $highestRow; $row++) {
        $poNumbers[] = (string) $sheet->getCell('A'.$row)->getValue();
    }

    expect($poNumbers)->toContain('PO-PENDING-001');
    expect($poNumbers)->not->toContain('PO-RECEIVED-001');
});

it('shows remaining quantity for partially received purchase orders in the po not yet delivered report', function () {
    $supplier = Supplier::query()->create([
        'code' => 'SUP-PO-NYD-002',
        'name' => 'PO NYD Partial Supplier',
        'created_by' => $this->manager->id,
    ]);

    $partialOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->manager->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-PARTIAL-001',
        'term_of_payment_type' => 'cash',
        'created_at' => now()->subDay(),
    ]);

    $partialItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $partialOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 10,
        'unit_price' => 100,
        'total' => 1000,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-NYD-002',
        'purchase_order_id' => $partialOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->manager->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $partialItem->id,
        'qty_good' => 4,
        'qty_bad' => 0,
    ]);

    $request = Request::create(
        route('procurement.reports.po-not-yet-delivered'),
        'POST',
        [
            'date_to' => now()->toDateString(),
            'po_type' => 'cash',
            'format' => 'excel',
        ],
    );
    $request->setUserResolver(fn () => $this->manager);

    $response = app(PurchasingReportController::class)->poNotYetDelivered($request);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    $tmp = tempnam(sys_get_temp_dir(), 'po-nyd-partial-xlsx');
    file_put_contents($tmp, $content);

    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }

    $highestRow = $sheet->getHighestDataRow();
    $partialRow = null;
    for ($row = 8; $row <= $highestRow; $row++) {
        if ((string) $sheet->getCell('A'.$row)->getValue() === 'PO-PARTIAL-001') {
            $partialRow = $row;
            break;
        }
    }

    expect($partialRow)->not->toBeNull();
    expect((float) $sheet->getCell('I'.$partialRow)->getValue())->toBe(6.0);
});

it('renders the po not yet delivered pdf with compact table sizing', function () {
    $html = view('pdf.reports.po-not-yet-delivered', array_merge(
        PdfReport::withDefaults([
            'title' => 'Purchase Order (PO) Not Yet Delivered',
            'as_of' => now()->toDateString(),
            'canvasser' => 'All',
            'po_type' => 'cash',
            'rows' => collect([
                [
                    'po_number' => 'PO-CASH-001',
                    'po_date' => now()->toDateString(),
                    'po_type' => 'cash',
                    'currency' => 'IDR',
                    'supplier_code' => 'SUP-001',
                    'supplier_name' => 'Supplier Name That Should Wrap Cleanly',
                    'item_code' => 'ITEM-001',
                    'item_name' => 'Item Description That Should Also Wrap Cleanly',
                    'quantity' => 2,
                    'unit' => 'PCS',
                    'unit_price' => 100,
                    'discount' => 0,
                    'pph' => 0,
                    'ppn' => 0,
                    'amount' => 200,
                    'currency_buckets' => [
                        'IDR' => 200,
                        'PHP' => 0,
                        'EUR' => 0,
                        'GBP' => 0,
                        'USD' => 0,
                        'YEN' => 0,
                    ],
                    'canvasser' => 'Lead Time Canvasser',
                    'remarks' => 'Long remarks should wrap and stay inside printable area.',
                ],
            ]),
        ]),
        [
            'fmtDate' => fn (mixed $value) => PdfFormatters::date($value),
            'fmtMoney' => fn (float|int|string $value) => PdfFormatters::money($value),
            'fmtQty' => fn (float|int|string $value) => PdfFormatters::qty($value),
        ],
    ))->render();

    expect($html)->toContain('po-not-delivered-table');
    expect($html)->toContain('table-layout: fixed');
    expect($html)->toContain('compact-wrap');
    expect($html)->toContain('<colgroup>');
});
