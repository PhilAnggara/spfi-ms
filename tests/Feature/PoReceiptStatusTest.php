<?php

use App\Enums\PoReceiptStatus;
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
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7215',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'Receipt Status User',
        'username' => 'receipt-status-user',
        'email' => 'receipt-status-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('purchasing-staff');

    $this->supplier = Supplier::query()->create([
        'name' => 'Receipt Supplier',
        'code' => 'SUP-RECEIPT-001',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create(['name' => 'Pieces', 'code' => 'PCS-RCPT']);
    $category = ItemCategory::query()->create(['name' => 'Office Supplies', 'code' => 'OFF-RCPT']);

    $this->item = Item::query()->create([
        'name' => 'Receipt Item',
        'code' => 'RCPT-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);
});

function createReceiptTestPo(User $user, Supplier $supplier, Item $item, float $quantity): PurchaseOrder
{
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RCPT-'.uniqid(),
        'subtotal' => $quantity * 100,
        'total' => $quantity * 100,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'unit_price' => 100,
        'total' => $quantity * 100,
    ]);

    return $purchaseOrder->fresh(['items']);
}

function receiveQty(PurchaseOrder $purchaseOrder, User $user, float $qtyGood): ReceivingReport
{
    $poItem = $purchaseOrder->items->first();

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-RCPT-'.uniqid(),
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $poItem->id,
        'qty_good' => $qtyGood,
        'qty_bad' => 0,
    ]);

    return $receivingReport;
}

it('resolves receipt status from ordered and received quantities', function (float $ordered, float $received, PoReceiptStatus $expected) {
    expect(PoReceiptStatus::fromQuantities($ordered, $received))->toBe($expected);
})->with([
    'none' => [10.0, 0.0, PoReceiptStatus::NotReceived],
    'zero ordered zero received' => [0.0, 0.0, PoReceiptStatus::NotReceived],
    'partial' => [10.0, 5.0, PoReceiptStatus::Partial],
    'full exact' => [10.0, 10.0, PoReceiptStatus::FullyReceived],
    'over received' => [10.0, 12.0, PoReceiptStatus::FullyReceived],
]);

it('exposes badge classes for receipt statuses', function () {
    expect(PoReceiptStatus::NotReceived->badgeClass())->toBe('badge bg-light-secondary text-secondary')
        ->and(PoReceiptStatus::Partial->badgeClass())->toBe('badge bg-light-warning text-warning')
        ->and(PoReceiptStatus::FullyReceived->badgeClass())->toBe('badge bg-light-success text-success');
});

it('shows not received badge on po list when no rr exists', function () {
    $purchaseOrder = createReceiptTestPo($this->user, $this->supplier, $this->item, 10);

    $this->actingAs($this->user)
        ->get(route('purchase-orders.index'))
        ->assertSuccessful()
        ->assertSee('Not Received')
        ->assertSee('badge bg-light-secondary text-secondary', false)
        ->assertSee('Receipt Status')
        ->assertSee($purchaseOrder->po_number);
});

it('shows partial badge and quantity summary when only some qty is received', function () {
    $purchaseOrder = createReceiptTestPo($this->user, $this->supplier, $this->item, 10);
    receiveQty($purchaseOrder, $this->user, 4);

    $this->actingAs($this->user)
        ->get(route('purchase-orders.index'))
        ->assertSuccessful()
        ->assertSee('Partial')
        ->assertSee('badge bg-light-warning text-warning', false)
        ->assertDontSee('DP 50% Credit')
        ->assertSee(format_po_decimal(4).' / '.format_po_decimal(10));
});

it('shows fully received badge when all qty is received', function () {
    $purchaseOrder = createReceiptTestPo($this->user, $this->supplier, $this->item, 8);
    receiveQty($purchaseOrder, $this->user, 8);

    $this->actingAs($this->user)
        ->get(route('purchase-orders.index'))
        ->assertSuccessful()
        ->assertSee('Fully Received')
        ->assertSee('badge bg-light-success text-success', false);
});
