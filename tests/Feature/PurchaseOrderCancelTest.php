<?php

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
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\DocumentNumberService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7401',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'PO Cancel Canvasser',
        'username' => 'po-cancel-canvasser',
        'email' => 'po-cancel-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->manager = User::query()->create([
        'name' => 'PO Cancel Manager',
        'username' => 'po-cancel-manager',
        'email' => 'po-cancel-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->otherStaff = User::query()->create([
        'name' => 'Other Cancel Staff',
        'username' => 'po-cancel-other',
        'email' => 'po-cancel-other@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->otherStaff->assignRole('purchasing-staff');

    $this->currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->canvasser->id,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-CANCEL-001',
        'name' => 'Cancel PO Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'Cancel Item A',
        'code' => 'CANCEL-A',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Cancel Item B',
        'code' => 'CANCEL-B',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '74010000111',
        'user_id' => $this->canvasser->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Cancel PO test PRS',
        'status' => 'PO_CREATED',
    ]);

    $this->prsItemA = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->prsItemB = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassingA = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $canvassingB = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemB->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 2000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItemA->update(['selected_canvassing_item_id' => $canvassingA->id]);
    $this->prsItemB->update(['selected_canvassing_item_id' => $canvassingB->id]);

    $this->releasedPoNumber = 'PO-PAPER-555';

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->canvasser->id,
        'status' => 'APPROVED',
        'po_number' => $this->releasedPoNumber,
        'subtotal' => 8000,
        'discount_amount' => 0,
        'ppn_amount' => 0,
        'pph_amount' => 0,
        'fees' => 0,
        'total' => 8000,
        'approved_at' => now(),
    ]);

    PurchaseOrderItem::query()->create([
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

    PurchaseOrderItem::query()->create([
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

it('lets the creator cancel an approved po and return items to draft', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.cancel', $this->purchaseOrder), [
            'cancel_reason' => 'Wrong items selected',
        ]);

    $response->assertRedirect(route('purchase-orders.index'));
    $response->assertSessionHas('success');

    $cancelled = PurchaseOrder::withTrashed()->find($this->purchaseOrder->id);

    expect($cancelled)->not->toBeNull()
        ->and($cancelled->trashed())->toBeTrue()
        ->and($cancelled->status)->toBe('CANCELLED')
        ->and($cancelled->po_number)->toBe('CANCELLED-'.$this->purchaseOrder->id)
        ->and($cancelled->approval_notes)->toContain($this->releasedPoNumber)
        ->and($cancelled->approval_notes)->toContain('Wrong items selected')
        ->and($this->prsItemA->fresh()->purchase_order_id)->toBeNull()
        ->and($this->prsItemB->fresh()->purchase_order_id)->toBeNull()
        ->and($this->prs->fresh()->status)->toBe('CANVASSING');

    $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.draft'))
        ->assertSuccessful()
        ->assertSee('Cancel Item A')
        ->assertSee('Cancel Item B');
});

it('lets a purchasing manager cancel an approved po', function () {
    $this->actingAs($this->manager)
        ->post(route('purchase-orders.cancel', $this->purchaseOrder))
        ->assertRedirect(route('purchase-orders.index'))
        ->assertSessionHas('success');

    expect(PurchaseOrder::withTrashed()->find($this->purchaseOrder->id)?->trashed())->toBeTrue();
});

it('releases the po number so it can be reused on a new po', function () {
    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.cancel', $this->purchaseOrder))
        ->assertRedirect(route('purchase-orders.index'));

    app(DocumentNumberService::class)->assertUnique('PO', $this->releasedPoNumber);

    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'po_number' => $this->releasedPoNumber,
            'remark_type' => 'Normal',
            'remark_text' => null,
            'term_of_payment_type' => 'cash',
            'term_of_payment' => 'COD',
            'term_of_delivery' => '7 days',
            'items' => [
                [
                    'prs_item_id' => $this->prsItemA->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(PurchaseOrder::query()->where('po_number', $this->releasedPoNumber)->exists())->toBeTrue();
});

it('blocks cancel when a receiving report already exists', function () {
    ReceivingReport::query()->create([
        'rr_number' => 'RR-CANCEL-001',
        'purchase_order_id' => $this->purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->canvasser->id,
    ]);

    $response = $this->actingAs($this->canvasser)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.cancel', $this->purchaseOrder));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('message');

    expect($this->purchaseOrder->fresh())->not->toBeNull()
        ->and($this->purchaseOrder->fresh()->status)->toBe('APPROVED')
        ->and($this->prsItemA->fresh()->purchase_order_id)->toBe($this->purchaseOrder->id);
});

it('rejects cancel when po is not approved', function () {
    $this->purchaseOrder->update(['status' => 'DRAFT']);

    $response = $this->actingAs($this->canvasser)
        ->from(route('purchase-orders.show', $this->purchaseOrder))
        ->post(route('purchase-orders.cancel', $this->purchaseOrder));

    $response->assertRedirect(route('purchase-orders.show', $this->purchaseOrder));
    $response->assertSessionHasErrors('message');
});

it('forbids another staff from cancelling a po they did not create', function () {
    $this->actingAs($this->otherStaff)
        ->post(route('purchase-orders.cancel', $this->purchaseOrder))
        ->assertForbidden();

    expect($this->purchaseOrder->fresh()->status)->toBe('APPROVED');
});

it('shows the cancel button on approved po detail for the creator', function () {
    $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.show', $this->purchaseOrder))
        ->assertSuccessful()
        ->assertSee('Cancel PO')
        ->assertSee('confirmCancelPo(', false)
        ->assertSee(route('purchase-orders.cancel', $this->purchaseOrder), false);
});
