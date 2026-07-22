<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7200',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'PO Print Layout User',
        'username' => 'po-print-layout-user',
        'email' => 'po-print-layout-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');

    $currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->user->id,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Print Supplier',
        'code' => 'SUP-PO-PRINT',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $item = Item::query()->create([
        'name' => 'Compact Item',
        'code' => 'ITM-PO-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-PRINT-001',
        'subtotal' => 100000,
        'discount_amount' => 5000,
        'ppn_amount' => 10450,
        'pph_amount' => 0,
        'fees' => 0,
        'total' => 105450,
        'signature_meta' => [
            'certified_by' => ['name' => 'Certifier Name'],
            'approved_by' => ['name' => 'Approver Name'],
        ],
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 10000,
        'line_subtotal' => 100000,
        'discount_rate' => 5,
        'discount_amount' => 5000,
        'ppn_rate' => 11,
        'ppn_amount' => 10450,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 105450,
        'meta' => ['prs_number' => 'PRS-001'],
    ]);
});

it('renders compact item table, three column summary, and aligned supplier signature', function () {
    $html = view('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('size: 215mm 160mm')
        ->toContain('class="po-main"')
        ->toContain('class="po-footer"')
        ->toContain('position: fixed')
        ->toContain('class="po-items"')
        ->toContain('class="item-name"')
        ->toContain('class="item-meta"')
        ->toContain('10 PCS')
        ->toContain('class="summary-table"')
        ->toContain('class="summary-middle"')
        ->toContain('5 %')
        ->toContain('11 %')
        ->toContain('class="signature-pad"')
        ->toContain('class="signature-blank"')
        ->toContain("Supplier's Signature")
        ->toContain('Denny Tuhatelu')
        ->not->toContain('class="po-sheet"')
        ->not->toContain('Certifier Name')
        ->not->toContain('Approver Name')
        ->not->toContain('Supplier\'s Signature : ____________________________');
});

it('prints approved by based on purchase order total threshold', function (float $total, string $approvedName) {
    $this->purchaseOrder->update(['total' => $total]);

    $html = view('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->fresh()->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->render();

    expect($html)
        ->toContain('Denny Tuhatelu')
        ->toContain($approvedName);
})->with([
    'below threshold' => [3_999_999.99, 'Denny Tuhatelu'],
    'at threshold' => [4_000_000.00, 'Sam Calamba'],
    'above threshold' => [4_000_000.01, 'Sam Calamba'],
]);

it('streams purchase order pdf on rr paper size as a single page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.print', $this->purchaseOrder));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => 215,
        'pageHeightMm' => 160,
    ])->setPaper([0, 0, 215 * 2.834645669, 160 * 2.834645669]);

    $dompdf = $pdf->getDomPDF();
    $dompdf->render();

    expect($dompdf->getCanvas()->get_page_count())->toBe(1);
});
