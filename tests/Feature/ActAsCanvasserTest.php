<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7601',
        'alias' => 'PUR',
    ]);

    $this->assignedCanvasser = User::query()->create([
        'name' => 'Assigned Canvasser',
        'username' => 'act-as-assigned',
        'email' => 'act-as-assigned@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->assignedCanvasser->assignRole('purchasing-staff');

    $this->helper = User::query()->create([
        'name' => 'Act As Helper',
        'username' => 'act-as-helper',
        'email' => 'act-as-helper@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->helper->assignRole('purchasing-staff');
    $this->helper->givePermissionTo('act-as-canvasser');

    $this->peer = User::query()->create([
        'name' => 'Peer Staff',
        'username' => 'act-as-peer',
        'email' => 'act-as-peer@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->peer->assignRole('purchasing-staff');

    $this->currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->helper->id,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-ACT-001',
        'name' => 'Act As Supplier',
        'created_by' => $this->helper->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Act As Item',
        'code' => 'ACT-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'act-as-creator',
        'email' => 'act-as-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => 'PRS-ACT-001',
        'prs_date' => now()->toDateString(),
        'user_id' => $creator->id,
        'department_id' => $this->department->id,
        'date_needed' => now()->addDays(7)->toDateString(),
        'status' => 'CANVASSING',
        'is_capex' => false,
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 3,
        'canvasser_id' => $this->assignedCanvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassing = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1500,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->assignedCanvasser->id,
    ]);

    $this->prsItem->update(['selected_canvassing_item_id' => $canvassing->id]);
});

it('seeds act-as-canvasser and view-fish-system permissions', function () {
    expect(Permission::findByName('act-as-canvasser'))->not->toBeNull();
    expect(Permission::findByName('view-fish-system'))->not->toBeNull();

    expect(User::role('purchasing-staff')->first()?->can('act-as-canvasser') ?? false)->toBeFalse();
});

it('shows other canvassers items on canvassing index with act-as-canvasser', function () {
    $this->actingAs($this->peer)
        ->get(route('canvassing.index'))
        ->assertSuccessful()
        ->assertDontSee('ACT-ITEM-001');

    $this->actingAs($this->helper)
        ->get(route('canvassing.index'))
        ->assertSuccessful()
        ->assertSee('ACT-ITEM-001')
        ->assertSee('Assigned Canvasser');
});

it('allows act-as-canvasser to open and save another canvassers item', function () {
    $this->actingAs($this->helper)
        ->get(route('canvassing.show', $this->prsItem))
        ->assertSuccessful()
        ->assertSee('Assigned to Assigned Canvasser');

    $this->actingAs($this->helper)
        ->post(route('canvassing.store', $this->prsItem), [
            'suppliers' => [
                [
                    'id' => $this->prsItem->selected_canvassing_item_id,
                    'supplier_id' => $this->supplier->id,
                    'unit_price' => 1750,
                    'lead_time_days' => 4,
                    'term_of_payment_type' => 'cash',
                    'notes' => 'Updated by helper',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->prsItem->fresh()->canvasser_id)->toBe($this->assignedCanvasser->id)
        ->and((float) $this->prsItem->canvassingItems()->first()->unit_price)->toBe(1750.0)
        ->and($this->prsItem->canvassingItems()->first()->canvased_by)->toBe($this->helper->id);
});

it('allows act-as-canvasser to hold another canvassers item', function () {
    $this->actingAs($this->helper)
        ->post(route('canvassing.hold', $this->prsItem), [
            'message' => 'Need quantity revision from helper.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->prs->fresh()->status)->toBe('CANVASSER_HOLD');

    $log = $this->prs->logs()->where('action', 'CANVASSER_HOLD')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->helper->id)
        ->and($log->meta['held_by'] ?? null)->toBe('canvasser_proxy')
        ->and((int) ($log->meta['assigned_canvasser_id'] ?? 0))->toBe($this->assignedCanvasser->id);
});

it('shows other canvassers items on draft po and can create po with act-as-canvasser', function () {
    $this->actingAs($this->peer)
        ->get(route('purchase-orders.draft'))
        ->assertSuccessful()
        ->assertDontSee('ACT-ITEM-001');

    $this->actingAs($this->helper)
        ->get(route('purchase-orders.draft'))
        ->assertSuccessful()
        ->assertSee('ACT-ITEM-001')
        ->assertSee('Assigned Canvasser');

    $this->actingAs($this->helper)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'submit',
            'remark_type' => 'Normal',
            'remark_text' => null,
            'term_of_payment_type' => 'materials_in_transit',
            'term_of_payment' => 'COD',
            'term_of_delivery' => '7 days',
            'items' => [
                [
                    'prs_item_id' => $this->prsItem->id,
                    'quantity' => 3,
                    'unit_price' => 1500,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $po = PurchaseOrder::query()->where('created_by', $this->helper->id)->latest('id')->first();

    expect($po)->not->toBeNull()
        ->and($po->status)->toBe('PENDING_APPROVAL')
        ->and($this->prsItem->fresh()->purchase_order_id)->toBe($po->id)
        ->and($this->prsItem->fresh()->canvasser_id)->toBe($this->assignedCanvasser->id);
});

it('forbids peer staff from viewing another canvassers item without act-as-canvasser', function () {
    $this->actingAs($this->peer)
        ->get(route('canvassing.show', $this->prsItem))
        ->assertForbidden();
});
