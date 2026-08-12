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

    $this->staff = User::query()->create([
        'name' => 'Purchasing Staff',
        'username' => 'sc-select-staff',
        'email' => 'sc-select-staff@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->staff->assignRole('purchasing-staff');

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'sc-select-canvasser',
        'email' => 'sc-select-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'sc-select-creator',
        'email' => 'sc-select-creator@example.test',
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
        'name' => 'Select Comparison Item',
        'code' => 'SEL-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'name' => 'Select Supplier',
        'code' => 'SUP-SEL-001',
        'created_by' => $this->canvasser->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Select comparison PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
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
});

it('saves a supplier selection over ajax and returns json', function () {
    $response = $this->actingAs($this->staff)
        ->postJson(route('procurement.supplier-comparison.select', $this->prsItem), [
            'canvassing_item_id' => $this->canvassingItem->id,
            'selection_reason' => 'Best lead time',
        ]);

    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'message' => 'Supplier selected for this item.',
            'prs_item_id' => $this->prsItem->id,
            'canvassing_item_id' => $this->canvassingItem->id,
            'selected_supplier_name' => 'Select Supplier',
            'selection_reason' => 'Best lead time',
        ])
        ->assertJsonPath('report_url', route('procurement.supplier-comparison.report', $this->prsItem));

    $this->prsItem->refresh();

    expect($this->prsItem->selected_canvassing_item_id)->toBe($this->canvassingItem->id)
        ->and($this->prsItem->selection_reason)->toBe('Best lead time');

    $log = PrsLog::query()
        ->where('prs_id', $this->prs->id)
        ->where('action', 'SELECT_SUPPLIER')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['canvassing_item_id'])->toBe($this->canvassingItem->id);

    Notification::assertSentTo($this->canvasser, ProcessNotification::class, function (ProcessNotification $notification) {
        return ($notification->payload['type'] ?? null) === 'supplier_selected';
    });
});

it('still redirects for non-ajax supplier selection', function () {
    $response = $this->actingAs($this->staff)
        ->from(route('procurement.supplier-comparison.index'))
        ->post(route('procurement.supplier-comparison.select', $this->prsItem), [
            'canvassing_item_id' => $this->canvassingItem->id,
            'selection_reason' => 'Redirect fallback',
        ]);

    $response->assertRedirect(route('procurement.supplier-comparison.index'));
    $response->assertSessionHas('success');

    expect($this->prsItem->fresh()->selected_canvassing_item_id)->toBe($this->canvassingItem->id);
});

it('returns a json error when supplier selection is locked by a purchase order', function () {
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->canvasser->id,
        'status' => 'DRAFT',
        'subtotal' => 7500,
        'total' => 7500,
    ]);

    $this->prsItem->update([
        'purchase_order_id' => $purchaseOrder->id,
        'selected_canvassing_item_id' => $this->canvassingItem->id,
    ]);

    $response = $this->actingAs($this->staff)
        ->postJson(route('procurement.supplier-comparison.select', $this->prsItem), [
            'canvassing_item_id' => $this->canvassingItem->id,
            'selection_reason' => 'Should fail',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Supplier selection is locked because a PO has been created.',
        ]);

    expect($this->prsItem->fresh()->selection_reason)->toBeNull();
});
