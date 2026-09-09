<?php

use App\Models\AccountingCode;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\ReceivingReportEntryGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7212',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'TOP Canvasser',
        'username' => 'top-canvasser',
        'email' => 'top-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('purchasing-staff');

    AccountingCode::query()->create(['code' => '201', 'desc' => 'ACCOUNTS PAYABLE - TRADE']);
    AccountingCode::query()->create(['code' => '122', 'desc' => 'ADVANCES TO OFFICERS AND EMPLOYEES']);
    AccountingCode::query()->create(['code' => '148', 'desc' => 'MATERIALS IN TRANSIT']);
    AccountingCode::query()->create(['code' => '153', 'desc' => 'OFFICE SUPPLIES']);

    $this->currency = Currency::query()->create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS-TOP']);
    $category = ItemCategory::query()->create(['name' => 'Office Supplies', 'code' => 'OFF-TOP']);

    $this->item = Item::query()->create([
        'name' => 'TOP Item',
        'code' => 'TOP-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-TOP-001',
        'name' => 'TOP Supplier',
        'created_by' => $this->user->id,
    ]);
});

function createReceivingReportForTerm(User $user, Item $item, Department $department, Supplier $supplier, string $termType): ReceivingReport
{
    $prs = Prs::query()->create([
        'prs_number' => 'PRS-TOP-'.uniqid(),
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'department_id' => $department->id,
        'user_id' => $user->id,
        'status' => 'PO_CREATED',
        'is_capex' => false,
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'status' => 'PO_CREATED',
        'canvasser_id' => $user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-TOP-'.uniqid(),
        'term_of_payment_type' => $termType,
    ]);

    $poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 1000,
        'total' => 2000,
        'line_subtotal' => 2000,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'meta' => ['term_of_payment_type' => $termType],
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-TOP-'.uniqid(),
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => 2,
        'qty_bad' => 0,
    ]);

    return $receivingReport->fresh()->load([
        'purchaseOrder.supplier',
        'purchaseOrder.currency',
        'purchaseOrder.items.prsItem.prs',
        'items.purchaseOrderItem.item.unit',
        'items.purchaseOrderItem.item.category',
        'items.purchaseOrderItem.prsItem.prs.department',
    ]);
}

it('maps rr credit accounts from term of payment type', function (string $termType, string $account) {
    $receivingReport = createReceivingReportForTerm(
        $this->user,
        $this->item,
        $this->department,
        $this->supplier,
        $termType
    );

    $payload = app(ReceivingReportEntryGenerator::class)->generate($receivingReport);
    $creditLine = collect($payload['lines'])->first(fn (array $line) => (float) ($line['credit'] ?? 0) > 0);

    expect($creditLine['account_code'] ?? null)->toBe($account);
})->with([
    'credit' => ['credit', '201'],
    'cash advance' => ['cash_advance', '122'],
    'materials in transit' => ['materials_in_transit', '148'],
    'legacy cash' => ['cash', '148'],
]);

it('stores cash advance on purchase order and item meta', function () {
    $prs = Prs::query()->create([
        'prs_number' => '72120000111',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'status' => 'CANVASSING',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
        'canvasser_id' => $this->user->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassing = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->user->id,
    ]);

    $prsItem->update(['selected_canvassing_item_id' => $canvassing->id]);

    $this->actingAs($this->user)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'remark_type' => 'Normal',
            'remark_text' => null,
            'term_of_payment_type' => 'cash_advance',
            'term_of_payment' => 'DP 50%',
            'term_of_delivery' => 'FOB',
            'items' => [
                [
                    'prs_item_id' => $prsItem->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $po = PurchaseOrder::query()->latest('id')->first();

    expect($po?->term_of_payment_type)->toBe('cash_advance')
        ->and($po?->items->first()?->meta['term_of_payment_type'] ?? null)->toBe('cash_advance');
});

it('rejects legacy cash value on purchase order store', function () {
    $prs = Prs::query()->create([
        'prs_number' => '72120000112',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'status' => 'CANVASSING',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->item->id,
        'quantity' => 1,
        'canvasser_id' => $this->user->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassing = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 500,
        'lead_time_days' => 3,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->user->id,
    ]);

    $prsItem->update(['selected_canvassing_item_id' => $canvassing->id]);

    $this->actingAs($this->user)
        ->from(route('purchase-orders.draft'))
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'remark_type' => 'Normal',
            'term_of_payment_type' => 'cash',
            'items' => [
                [
                    'prs_item_id' => $prsItem->id,
                    'quantity' => 1,
                    'unit_price' => 500,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('term_of_payment_type');
});

it('includes cash advance pos when filtering undelivered report by cash', function () {
    $this->user->assignRole('purchasing-manager');

    $prs = Prs::query()->create([
        'prs_number' => '72120000113',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'status' => 'APPROVED',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'canvasser_id' => $this->user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-CASH-ADV-001',
        'term_of_payment_type' => 'cash_advance',
        'created_at' => now()->subDay(),
        'total' => 3000,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'prs_item_id' => $prsItem->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'unit_price' => 1000,
        'total' => 3000,
        'meta' => ['term_of_payment_type' => 'cash_advance'],
    ]);

    $response = $this->actingAs($this->user)->post(route('procurement.reports.po-not-yet-delivered'), [
        'date_to' => now()->toDateString(),
        'po_type' => 'cash',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();

    $tmp = tempnam(sys_get_temp_dir(), 'top-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        expect((string) $sheet->getCell('A8')->getValue())->toBe('PO-CASH-ADV-001');
    } finally {
        @unlink($tmp);
    }
});

it('preselects materials in transit on po preview when canvassing is cash', function () {
    $prs = Prs::query()->create([
        'prs_number' => '72120000114',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'status' => 'CANVASSING',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->item->id,
        'quantity' => 1,
        'canvasser_id' => $this->user->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassing = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 750,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->user->id,
    ]);

    $prsItem->update(['selected_canvassing_item_id' => $canvassing->id]);

    $this->actingAs($this->user)
        ->post(route('purchase-orders.preview'), [
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'prs_item_id' => $prsItem->id,
                    'quantity' => 1,
                    'unit_price' => 750,
                    'checked' => '1',
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertSee('value="materials_in_transit"', false)
        ->assertSee('Cash Advance')
        ->assertSee('Materials In Transit')
        ->assertDontSee('value="cash"', false);
});
