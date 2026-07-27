<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\PrsLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Notifications\ProcessNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->manager = User::query()->create([
        'name' => 'Purchasing Manager',
        'username' => 'sc-reject-manager',
        'email' => 'sc-reject-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->staff = User::query()->create([
        'name' => 'Purchasing Staff',
        'username' => 'sc-reject-staff',
        'email' => 'sc-reject-staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->staff->assignRole('purchasing-staff');

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'sc-reject-canvasser',
        'email' => 'sc-reject-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'sc-reject-creator',
        'email' => 'sc-reject-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
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
        'name' => 'Reject Comparison Item',
        'code' => 'REJ-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'name' => 'Reject Supplier',
        'code' => 'SUP-REJ-001',
        'created_by' => $this->canvasser->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Reject comparison PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
        'selection_reason' => 'Lowest price',
    ]);

    $this->canvassingItem = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1500,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItem->update([
        'selected_canvassing_item_id' => $this->canvassingItem->id,
    ]);
});

it('lets a purchasing manager reject canvassing while keeping quotes', function () {
    $response = $this->actingAs($this->manager)
        ->from(route('procurement.supplier-comparison.index'))
        ->post(route('procurement.supplier-comparison.reject', $this->prsItem), [
            'rejection_reason' => 'Need better lead time',
        ]);

    $response->assertRedirect(route('procurement.supplier-comparison.index'));
    $response->assertSessionHas('success');

    $this->prsItem->refresh();

    expect($this->prsItem->selected_canvassing_item_id)->toBeNull()
        ->and($this->prsItem->selection_reason)->toBeNull()
        ->and($this->prsItem->canvassingItems()->count())->toBe(1);

    $log = PrsLog::query()
        ->where('prs_id', $this->prs->id)
        ->where('action', 'REJECT_SUPPLIER')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['prs_item_id'])->toBe($this->prsItem->id)
        ->and($log->meta['previous_canvassing_item_id'])->toBe($this->canvassingItem->id)
        ->and($log->meta['rejection_reason'])->toBe('Need better lead time');

    Notification::assertSentTo($this->canvasser, ProcessNotification::class, function (ProcessNotification $notification) {
        return ($notification->payload['type'] ?? null) === 'supplier_rejected'
            && str_contains((string) ($notification->payload['message'] ?? ''), 'Need better lead time');
    });
});

it('allows rejecting without a reason', function () {
    $response = $this->actingAs($this->manager)
        ->from(route('procurement.supplier-comparison.index'))
        ->post(route('procurement.supplier-comparison.reject', $this->prsItem));

    $response->assertRedirect(route('procurement.supplier-comparison.index'));

    $log = PrsLog::query()
        ->where('prs_id', $this->prs->id)
        ->where('action', 'REJECT_SUPPLIER')
        ->latest('id')
        ->first();

    expect($this->prsItem->fresh()->selected_canvassing_item_id)->toBeNull()
        ->and($log?->meta['rejection_reason'])->toBeNull();
});

it('restores canvassing status and list visibility after reject on partially po-created prs', function () {
    $siblingItem = Item::query()->create([
        'name' => 'Reject Sibling Item',
        'code' => 'REJ-SIBLING-001',
        'unit_of_measure_id' => UnitOfMeasure::query()->first()->id,
        'category_id' => ItemCategory::query()->first()->id,
        'type' => 'Consumable',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    $siblingPrsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $siblingItem->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $siblingQuote = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $siblingPrsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 2500,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->canvasser->id,
        'status' => 'DRAFT',
        'subtotal' => 5000,
        'total' => 5000,
    ]);

    $siblingPrsItem->update([
        'selected_canvassing_item_id' => $siblingQuote->id,
        'purchase_order_id' => $purchaseOrder->id,
    ]);

    $this->prs->update(['status' => 'PO_CREATED']);

    $response = $this->actingAs($this->manager)
        ->from(route('procurement.supplier-comparison.index'))
        ->post(route('procurement.supplier-comparison.reject', $this->prsItem), [
            'rejection_reason' => 'Need better price',
        ]);

    $response->assertRedirect(route('procurement.supplier-comparison.index'));
    $response->assertSessionHas('success');

    expect($this->prs->fresh()->status)->toBe('CANVASSING')
        ->and($this->prsItem->fresh()->selected_canvassing_item_id)->toBeNull();

    $this->actingAs($this->canvasser)
        ->get(route('canvassing.index'))
        ->assertSuccessful()
        ->assertSee('REJ-ITEM-001')
        ->assertDontSee('REJ-SIBLING-001');
});

it('blocks reject when a purchase order is already linked', function () {
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->canvasser->id,
        'status' => 'DRAFT',
        'subtotal' => 7500,
        'total' => 7500,
    ]);

    $this->prsItem->update(['purchase_order_id' => $purchaseOrder->id]);

    $response = $this->actingAs($this->manager)
        ->from(route('procurement.supplier-comparison.index'))
        ->post(route('procurement.supplier-comparison.reject', $this->prsItem), [
            'rejection_reason' => 'Too late',
        ]);

    $response->assertRedirect(route('procurement.supplier-comparison.index'));
    $response->assertSessionHasErrors('message');

    expect($this->prsItem->fresh()->selected_canvassing_item_id)->toBe($this->canvassingItem->id);
});

it('forbids purchasing staff from rejecting canvassing', function () {
    $this->actingAs($this->staff)
        ->post(route('procurement.supplier-comparison.reject', $this->prsItem))
        ->assertForbidden();

    expect($this->prsItem->fresh()->selected_canvassing_item_id)->toBe($this->canvassingItem->id);
});

it('shows the reject action for managers on the comparison page', function () {
    $response = $this->actingAs($this->manager)
        ->get(route('procurement.supplier-comparison.index'));

    $response->assertSuccessful();
    $response->assertSee('Reject Canvassing');
    $response->assertSee(
        'action="'.route('procurement.supplier-comparison.reject', $this->prsItem).'"',
        false
    );
});
