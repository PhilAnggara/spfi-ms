<?php

use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7100',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'RR Print User',
        'username' => 'rr-print-user',
        'email' => 'rr-print-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');

    $supplier = Supplier::query()->create([
        'name' => 'Test Supplier',
        'code' => 'SUP-001',
        'created_by' => $this->user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TEST-001',
    ]);

    $this->receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-TEST-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);
});

it('renders receiving report pdf preview with custom paper size', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.print', [
            'receivingReport' => $this->receivingReport,
            'mode' => 'preview',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders receiving report pdf print with custom paper size', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.print', [
            'receivingReport' => $this->receivingReport,
            'mode' => 'print',
        ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('uses 215mm by 160mm page dimensions in receiving report view', function () {
    $html = view('pdf.receiving-report', [
        'receivingReport' => $this->receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]),
        'isPreview' => true,
        'approvedByName' => 'Approver',
        'backgroundImageDataUri' => null,
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('size: 215mm 160mm')
        ->toContain('width: 215mm')
        ->toContain('height: 160mm');
});
