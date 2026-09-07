<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
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
        'username' => 'prs-pm-hold-manager',
        'email' => 'prs-pm-hold-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'prs-pm-hold-canvasser',
        'email' => 'prs-pm-hold-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'prs-pm-hold-creator',
        'email' => 'prs-pm-hold-creator@example.test',
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

    $this->catalogItem = Item::query()->create([
        'name' => 'Hold Item Alpha',
        'code' => 'HLD-ALPHA',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->replacementCatalogItem = Item::query()->create([
        'name' => 'Hold Item Beta',
        'code' => 'HLD-BETA',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    $this->currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->manager->id,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-HLD-001',
        'name' => 'Hold Supplier',
        'created_by' => $this->manager->id,
    ]);
});

function createCanvassingPrsForPmHold(): Prs
{
    $prs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => test()->creator->id,
        'department_id' => test()->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'PM hold canvassing PRS',
        'status' => 'CANVASSING',
    ]);

    PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => test()->catalogItem->id,
        'quantity' => 4,
        'canvasser_id' => test()->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    return $prs->fresh(['items']);
}

it('lets purchasing manager hold a canvassing prs', function () {
    $prs = createCanvassingPrsForPmHold();

    $response = $this->actingAs($this->manager)
        ->post(route('prs.hold', $prs), [
            'message' => 'Specification is unclear and needs full revision.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $prs->refresh();
    expect($prs->status)->toBe('ON_HOLD');
    expect($prs->logs()->where('action', 'HOLD')->exists())->toBeTrue();
    expect($prs->logs()->where('action', 'HOLD')->latest('id')->first()->meta['previous_status'])->toBe('CANVASSING');

    Notification::assertSentTo($this->creator, ProcessNotification::class, function (ProcessNotification $notification) {
        return ($notification->payload['type'] ?? null) === 'prs_on_hold';
    });
    Notification::assertSentTo($this->canvasser, ProcessNotification::class, function (ProcessNotification $notification) {
        return ($notification->payload['type'] ?? null) === 'prs_on_hold';
    });
});

it('lets purchasing manager hold a canvasser-hold prs for full edit', function () {
    $prs = createCanvassingPrsForPmHold();
    $prs->update(['status' => 'CANVASSER_HOLD']);

    $this->actingAs($this->manager)
        ->post(route('prs.hold', $prs), [
            'message' => 'Item list must change, not only quantity.',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $prs->refresh();
    expect($prs->status)->toBe('ON_HOLD');
    expect($prs->logs()->where('action', 'HOLD')->latest('id')->first()->meta['previous_status'])->toBe('CANVASSER_HOLD');
});

it('rejects purchasing manager hold when an item already has a purchase order', function () {
    $prs = createCanvassingPrsForPmHold();
    $item = $prs->items()->first();

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->manager->id,
        'status' => 'DRAFT',
        'po_number' => 'PO-HLD-LOCKED',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $item->update(['purchase_order_id' => $purchaseOrder->id]);

    $this->actingAs($this->manager)
        ->from(route('prs.approval.index'))
        ->post(route('prs.hold', $prs), [
            'message' => 'Should not hold.',
        ])
        ->assertRedirect(route('prs.approval.index'))
        ->assertSessionHasErrors('message');

    expect($prs->fresh()->status)->toBe('CANVASSING');
});

it('rejects purchasing manager hold when an item is marked direct purchase', function () {
    $prs = createCanvassingPrsForPmHold();
    $prs->items()->first()->update(['is_direct_purchase' => true]);

    $this->actingAs($this->manager)
        ->from(route('prs.approval.index'))
        ->post(route('prs.hold', $prs), [
            'message' => 'Should not hold.',
        ])
        ->assertRedirect(route('prs.approval.index'))
        ->assertSessionHasErrors('message');

    expect($prs->fresh()->status)->toBe('CANVASSING');
});

it('lets requester fully edit after purchasing manager hold and moves to revised', function () {
    $prs = createCanvassingPrsForPmHold();

    $this->actingAs($this->manager)
        ->post(route('prs.hold', $prs), [
            'message' => 'Please revise the item list.',
        ])
        ->assertSessionHas('success');

    $this->actingAs($this->creator)
        ->put(route('prs.update', $prs), [
            'department_id' => $this->department->id,
            'date_needed' => now()->addDays(10)->toDateString(),
            'is_capex' => '0',
            'remarks' => 'Revised after canvassing hold',
            'prsItems' => [
                [
                    'item_id' => $this->replacementCatalogItem->id,
                    'quantity' => 7,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $prs->refresh();
    $activeItems = $prs->items()->get();

    expect($prs->status)->toBe('REVISED')
        ->and($prs->remarks)->toBe('Revised after canvassing hold')
        ->and($activeItems)->toHaveCount(1)
        ->and($activeItems->first()->item_id)->toBe($this->replacementCatalogItem->id)
        ->and($activeItems->first()->canvasser_id)->toBeNull()
        ->and((float) $activeItems->first()->quantity)->toBe(7.0)
        ->and($prs->logs()->where('action', 'RESUBMIT')->exists())->toBeTrue();
});

it('disables hold button on approval index when canvassing item has a purchase order', function () {
    $prs = createCanvassingPrsForPmHold();
    $item = $prs->items()->first();

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->manager->id,
        'status' => 'DRAFT',
        'po_number' => 'PO-HLD-UI',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $item->update(['purchase_order_id' => $purchaseOrder->id]);

    $response = $this->actingAs($this->manager)
        ->get(route('prs.approval.index'));

    $response->assertSuccessful()
        ->assertSee('data-bs-target="#hold-modal-'.$prs->id.'"', false)
        ->assertSee('disabled', false);
});
