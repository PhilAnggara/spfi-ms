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
    $this->purchaseOrder->update([
        'term_of_payment_type' => 'credit',
        'term_of_payment' => '30 days',
    ]);

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
        ->toContain('size: 215mm 160mm')
        ->toContain('font-family: Arial, sans-serif')
        ->toContain('font-size: 11px')
        ->toContain('class="po-main"')
        ->toContain('class="po-footer"')
        ->toContain('position: fixed')
        ->toContain('po-supplier')
        ->toContain('<strong>Print Supplier</strong>')
        ->toContain('CREDIT • 30 days')
        ->toContain('background: none')
        ->toContain('class="po-items"')
        ->toContain('>Item Name</th>')
        ->toContain('>Item Code</th>')
        ->toContain('class="item-name"')
        ->toContain('class="col-item-code"')
        ->toContain('ITM-PO-001')
        ->toContain('>Unit</th>')
        ->toContain('class="col-unit"')
        ->toContain('>PCS</td>')
        ->toContain('class="col-qty"')
        ->toContain('white-space: nowrap')
        ->toContain('>Price</th>')
        ->toContain('>Amount</th>')
        ->toContain('class="text-right col-amount">100.000,00</td>')
        ->toContain('.po-items th,')
        ->toContain('border: none')
        ->toContain('.po-items thead th')
        ->toContain('border-bottom: 1px solid #111827')
        ->toContain('class="summary-table"')
        ->toContain('class="summary-middle"')
        ->toContain('5 %')
        ->toContain('11 %')
        ->toContain('class="po-delivery"')
        ->toContain('class="po-number"')
        ->toContain('Delivery to PT Sinar Pure Foods International |')
        ->toContain('<span class="po-number">PO-PRINT-001</span>')
        ->toContain('class="signature-pad"')
        ->toContain('class="signature-blank"')
        ->toContain("Supplier's Signature")
        ->toContain('Denny Tuhatelu')
        ->not->toContain('class="text-right col-amount">105.450,00</td>')
        ->not->toContain('width: 64px')
        ->not->toContain('col-qty col-qty-wrap')
        ->not->toContain('class="item-meta"')
        ->not->toContain('>Item</th>')
        ->not->toContain('10 PCS')
        ->not->toContain('30 days • credit')
        ->not->toContain('(<strong>PO Number</strong>')
        ->not->toContain('>Disc</th>')
        ->not->toContain('>PPN</th>')
        ->not->toContain('>PPh</th>')
        ->not->toContain('5,0%')
        ->not->toContain('11,0%')
        ->not->toContain('font-family: DejaVu Sans')
        ->not->toContain('background: #f3f4f6')
        ->not->toContain('class="po-sheet"')
        ->not->toContain('Certifier Name')
        ->not->toContain('Approver Name')
        ->not->toContain('Supplier\'s Signature : ____________________________');
});

it('allows qty column to wrap only when qty display is very long', function () {
    $this->purchaseOrder->items()->first()->update([
        'quantity' => 1250000000000,
    ]);

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
        ->toContain('col-qty-wrap')
        ->toContain('1.250.000.000.000')
        ->toContain('class="col-unit">PCS</td>');
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
    'at threshold' => [4_000_000.00, 'S.C Calamba, Jr'],
    'above threshold' => [4_000_000.01, 'S.C Calamba, Jr'],
]);

it('prints approved by S.C Calamba, Jr for non-IDR currencies regardless of total', function () {
    $usd = Currency::query()->create([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'created_by' => $this->user->id,
    ]);

    $this->purchaseOrder->update([
        'currency_id' => $usd->id,
        'total' => 100,
    ]);

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
        ->toContain('S.C Calamba, Jr')
        ->toContain('USD');
});

it('streams purchase order pdf on rr paper size as a single page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.print', $this->purchaseOrder));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toContain('/PrintScaling /None');

    $pageWidthMm = (float) config('purchase-order.paper.width_mm');
    $pageHeightMm = (float) config('purchase-order.paper.height_mm');

    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.purchase-order', [
        'purchaseOrder' => $this->purchaseOrder->load([
            'supplier',
            'currency',
            'items.item.unit',
            'items.prsItem.prs.department',
            'certifiedBy',
            'approvedBy',
        ]),
        'pageWidthMm' => $pageWidthMm,
        'pageHeightMm' => $pageHeightMm,
    ])->setPaper([0, 0, $pageWidthMm * 2.834645669, $pageHeightMm * 2.834645669]);

    $dompdf = $pdf->getDomPDF();
    $dompdf->render();

    expect($dompdf->getCanvas()->get_page_count())->toBe(1);
});

it('shows paper form size and print checklist in confirm print modal', function () {
    $modal = view('pages.purchase-orders.partials.print-confirm-modal', [
        'purchaseOrder' => $this->purchaseOrder,
        'nextPoNumber' => 'PO-NEXT-001',
        'errors' => new Illuminate\Support\ViewErrorBag,
    ])->render();

    $paperLabel = config('purchase-order.paper.label');
    $paperWidthMm = (string) config('purchase-order.paper.width_mm');
    $paperHeightMm = (string) config('purchase-order.paper.height_mm');

    expect($modal)
        ->toContain($paperLabel)
        ->toContain($paperWidthMm)
        ->toContain($paperHeightMm)
        ->toContain('Decimal places')
        ->toContain('name="decimal_places"')
        ->toContain('2 (default)')
        ->toContain('Actual size / 100%')
        ->toContain('Fit to page')
        ->toContain('Portrait');

    $response = $this->actingAs($this->user)
        ->get(route('purchase-orders.index'));

    $response->assertSuccessful();
    $response->assertSee($paperLabel, false);
    $response->assertSee('Decimal places', false);
    $response->assertSee('Actual size / 100%', false);
});
