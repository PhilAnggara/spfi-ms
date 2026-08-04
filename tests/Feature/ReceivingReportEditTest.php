<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory RR Edit',
        'code' => '7101',
        'alias' => 'IM-RR-EDIT',
    ]);

    $this->user = User::query()->create([
        'name' => 'RR Edit User',
        'username' => 'rr-edit-user',
        'email' => 'rr-edit-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $supplier = Supplier::query()->create([
        'name' => 'RR Edit Supplier',
        'code' => 'SUP-RR-EDIT',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-RR-EDIT',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables RR Edit',
        'code' => 'CNS-RR-EDIT',
    ]);

    $this->item = Item::query()->create([
        'name' => 'RR Edit Item',
        'code' => 'ITM-RR-EDIT',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 50,
        'start_balance' => 50,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RR-EDIT-001',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 20,
        'unit_price' => 100,
        'line_subtotal' => 2000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 2000,
    ]);
});

function createReceivingReportForEdit(object $context, float $qtyGood = 10, float $qtyBad = 0): ReceivingReport
{
    $response = test()->actingAs($context->user)
        ->from(route('receiving-reports.index'))
        ->post(route('receiving-reports.store'), [
            'rr_number' => 'RR-EDIT-'.uniqid(),
            'purchase_order_id' => $context->purchaseOrder->id,
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => 'Initial RR',
            'items' => [
                [
                    'purchase_order_item_id' => $context->purchaseOrderItem->id,
                    'selected' => '1',
                    'qty_good' => $qtyGood,
                    'qty_bad' => $qtyBad,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasNoErrors();

    return ReceivingReport::query()
        ->where('purchase_order_id', $context->purchaseOrder->id)
        ->whereNull('deleted_at')
        ->latest('id')
        ->firstOrFail();
}

it('updates receiving report quantities without unique constraint error', function () {
    $receivingReport = createReceivingReportForEdit($this, 10);

    expect(ReceivingReportItem::query()->where('receiving_report_id', $receivingReport->id)->count())->toBe(1)
        ->and((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(60.0);

    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->put(route('receiving-reports.update', $receivingReport), [
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => 'Updated RR',
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'selected' => '1',
                    'qty_good' => 7,
                    'qty_bad' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $receivingReport->refresh();
    expect($receivingReport->notes)->toBe('Updated RR')
        ->and(ReceivingReportItem::query()->where('receiving_report_id', $receivingReport->id)->count())->toBe(1)
        ->and((float) $receivingReport->items()->first()->qty_good)->toBe(7.0);
});

it('adjusts stock when receiving report qty good is edited', function () {
    $receivingReport = createReceivingReportForEdit($this, 10);

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(60.0)
        ->and((float) Item::query()->where('id', $this->item->id)->value('stock_on_hand'))->toBe(60.0);

    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->put(route('receiving-reports.update', $receivingReport), [
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => 'Stock adjust',
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'selected' => '1',
                    'qty_good' => 15,
                    'qty_bad' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasNoErrors();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(65.0)
        ->and((float) Item::query()->where('id', $this->item->id)->value('stock_on_hand'))->toBe(65.0);

    $netIn = round((float) StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $receivingReport->id)
        ->where('reference_line_id', $this->purchaseOrderItem->id)
        ->selectRaw('COALESCE(SUM(qty_in1), 0) - COALESCE(SUM(qty_out1), 0) as net_in')
        ->value('net_in'), 5);

    expect($netIn)->toBe(15.0);

    $response = $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->put(route('receiving-reports.update', $receivingReport), [
            'received_date' => now()->toDateString(),
            'requires_customs_document' => '0',
            'notes' => 'Stock reduce',
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'selected' => '1',
                    'qty_good' => 4,
                    'qty_bad' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('receiving-reports.index'));
    $response->assertSessionHasNoErrors();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(54.0)
        ->and((float) Item::query()->where('id', $this->item->id)->value('stock_on_hand'))->toBe(54.0);

    $netInAfterReduce = round((float) StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $receivingReport->id)
        ->where('reference_line_id', $this->purchaseOrderItem->id)
        ->selectRaw('COALESCE(SUM(qty_in1), 0) - COALESCE(SUM(qty_out1), 0) as net_in')
        ->value('net_in'), 5);

    expect($netInAfterReduce)->toBe(4.0);
});
