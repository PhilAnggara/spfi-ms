<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
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

    $this->canvasser = User::query()->create([
        'name' => 'PO Withdraw Canvasser',
        'username' => 'po-withdraw-canvasser',
        'email' => 'po-withdraw-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->otherStaff = User::query()->create([
        'name' => 'Other Purchasing Staff',
        'username' => 'po-other-staff',
        'email' => 'po-other-staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->otherStaff->assignRole('purchasing-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'Withdraw Item A',
        'code' => 'WD-ITEM-A',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Withdraw Item B',
        'code' => 'WD-ITEM-B',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-WD-001',
        'name' => 'Withdraw Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => '71010000888',
        'user_id' => $this->canvasser->id,
        'department_id' => $department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Withdraw test PRS',
        'status' => 'PO_CREATED',
    ]);

    $this->prsItemA = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->prsItemB = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassingA = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $canvassingB = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $this->prsItemB->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 2000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItemA->update(['selected_canvassing_item_id' => $canvassingA->id]);
    $this->prsItemB->update(['selected_canvassing_item_id' => $canvassingB->id]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->canvasser->id,
        'status' => 'PENDING_APPROVAL',
        'po_number' => 'PO-WD-001',
        'subtotal' => 8000,
        'discount_amount' => 0,
        'ppn_amount' => 0,
        'pph_amount' => 0,
        'fees' => 0,
        'total' => 8000,
        'submitted_at' => now(),
    ]);

    $this->poItemA = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'prs_item_id' => $this->prsItemA->id,
        'item_id' => $this->itemA->id,
        'quantity' => 2,
        'unit_price' => 1000,
        'line_subtotal' => 2000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 2000,
    ]);

    $this->poItemB = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'prs_item_id' => $this->prsItemB->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'unit_price' => 2000,
        'line_subtotal' => 6000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 6000,
    ]);

    $this->prsItemA->update(['purchase_order_id' => $this->purchaseOrder->id]);
    $this->prsItemB->update(['purchase_order_id' => $this->purchaseOrder->id]);
});

it('lets the creator withdraw a pending approval po to draft', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.withdraw', $this->purchaseOrder));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHas('success');

    $this->purchaseOrder->refresh();

    expect($this->purchaseOrder->status)->toBe('DRAFT')
        ->and($this->purchaseOrder->submitted_at)->toBeNull();
});

it('forbids another staff from withdrawing a po', function () {
    $response = $this->actingAs($this->otherStaff)
        ->post(route('purchase-orders.withdraw', $this->purchaseOrder));

    $response->assertForbidden();

    expect($this->purchaseOrder->fresh()->status)->toBe('PENDING_APPROVAL');
});

it('rejects withdraw when po is not pending approval', function () {
    $this->purchaseOrder->update([
        'status' => 'DRAFT',
        'submitted_at' => null,
    ]);

    $response = $this->actingAs($this->canvasser)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.withdraw', $this->purchaseOrder));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('message');
});

it('removes a po item and returns the prs item to the draft queue', function () {
    $this->purchaseOrder->update([
        'status' => 'CHANGES_REQUESTED',
        'submitted_at' => null,
        'approval_notes' => 'Please remove one item',
    ]);

    $response = $this->actingAs($this->canvasser)
        ->delete(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemB]));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHas('success');

    expect(PurchaseOrderItem::query()->find($this->poItemB->id))->toBeNull();

    $this->prsItemB->refresh();
    $this->purchaseOrder->refresh();

    expect($this->prsItemB->purchase_order_id)->toBeNull()
        ->and($this->purchaseOrder->items()->count())->toBe(1)
        ->and((float) $this->purchaseOrder->subtotal)->toBe(2000.0)
        ->and((float) $this->purchaseOrder->total)->toBe(2000.0);

    $draftResponse = $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.draft'));

    $draftResponse->assertSuccessful();
    $draftResponse->assertSee('Withdraw Item B');
    $draftResponse->assertDontSee('Withdraw Item A');
});

it('does not allow removing the last remaining po item', function () {
    $this->purchaseOrder->update([
        'status' => 'DRAFT',
        'submitted_at' => null,
    ]);

    $this->actingAs($this->canvasser)
        ->delete(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemB]))
        ->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));

    $response = $this->actingAs($this->canvasser)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->delete(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemA]));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('items');

    expect(PurchaseOrderItem::query()->find($this->poItemA->id))->not->toBeNull()
        ->and($this->prsItemA->fresh()->purchase_order_id)->toBe($this->purchaseOrder->id);
});

it('forbids another staff from removing a po item', function () {
    $this->purchaseOrder->update([
        'status' => 'DRAFT',
        'submitted_at' => null,
    ]);

    $response = $this->actingAs($this->otherStaff)
        ->delete(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemB]));

    $response->assertForbidden();

    expect(PurchaseOrderItem::query()->find($this->poItemB->id))->not->toBeNull()
        ->and($this->prsItemB->fresh()->purchase_order_id)->toBe($this->purchaseOrder->id);
});

it('rejects removing an item while po is pending approval', function () {
    $response = $this->actingAs($this->canvasser)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->delete(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemB]));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('message');

    expect(PurchaseOrderItem::query()->find($this->poItemB->id))->not->toBeNull();
});

it('shows withdraw button for pending approval and remove controls after withdraw', function () {
    $pendingPage = $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.show', $this->purchaseOrder));

    $pendingPage->assertSuccessful();
    $pendingPage->assertSee('Withdraw to Draft');
    $pendingPage->assertDontSee(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemA]), false);

    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.withdraw', $this->purchaseOrder));

    $draftPage = $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.show', $this->purchaseOrder));

    $draftPage->assertSuccessful();
    $draftPage->assertSee('Save Changes');
    $draftPage->assertSee(route('purchase-orders.items.destroy', [$this->purchaseOrder, $this->poItemA]), false);
});
