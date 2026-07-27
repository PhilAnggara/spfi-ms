<?php

use App\Models\Currency;
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
        'username' => 'prs-assign-manager',
        'email' => 'prs-assign-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'prs-assign-canvasser',
        'email' => 'prs-assign-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->replacementCanvasser = User::query()->create([
        'name' => 'Replacement Canvasser',
        'username' => 'prs-assign-canvasser-2',
        'email' => 'prs-assign-canvasser-2@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->replacementCanvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'prs-assign-creator',
        'email' => 'prs-assign-creator@example.test',
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

    $this->firstCatalogItem = Item::query()->create([
        'name' => 'Assignment Item Alpha',
        'code' => 'ASN-ALPHA',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->secondCatalogItem = Item::query()->create([
        'name' => 'Assignment Item Beta',
        'code' => 'ASN-BETA',
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
        'code' => 'SUP-ASN-001',
        'name' => 'Assignment Supplier',
        'created_by' => $this->manager->id,
    ]);
});

function createRequestedPrsWithItems(int $itemCount): Prs
{
    $prs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => test()->creator->id,
        'department_id' => test()->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Assignment PRS',
        'status' => 'REQUESTED',
    ]);

    $catalogItems = [
        test()->firstCatalogItem,
        test()->secondCatalogItem,
    ];

    for ($index = 0; $index < $itemCount; $index++) {
        PrsItem::query()->create([
            'prs_id' => $prs->id,
            'item_id' => $catalogItems[$index % count($catalogItems)]->id,
            'quantity' => $index + 1,
        ]);
    }

    return $prs->fresh(['items']);
}

function assignPrsToCanvassing(Prs $prs, User $canvasser): Prs
{
    $items = $prs->items()->orderBy('id')->get();

    test()->actingAs(test()->manager)
        ->post(route('prs.approve', $prs), [
            'items' => $items->map(fn (PrsItem $item) => [
                'prs_item_id' => $item->id,
                'canvasser_id' => $canvasser->id,
            ])->all(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    return $prs->fresh(['items']);
}

it('shows apply to all helper only for multi item prs', function () {
    $multiItemPrs = createRequestedPrsWithItems(2);
    $singleItemPrs = createRequestedPrsWithItems(1);

    $response = $this->actingAs($this->manager)
        ->get(route('prs.approval.index'));

    $response->assertSuccessful()
        ->assertSee('assign-canvasser-bulk', false)
        ->assertSee('id="assign-canvasser-bulk-'.$multiItemPrs->id.'"', false)
        ->assertDontSee('id="assign-canvasser-bulk-'.$singleItemPrs->id.'"', false)
        ->assertDontSee('assign-canvasser-bulk-apply', false);
});

it('assigns the same canvasser to all items in one approve request', function () {
    $prs = createRequestedPrsWithItems(2);
    $items = $prs->items()->orderBy('id')->get();

    $this->actingAs($this->manager)
        ->post(route('prs.approve', $prs), [
            'items' => [
                [
                    'prs_item_id' => $items[0]->id,
                    'canvasser_id' => $this->canvasser->id,
                ],
                [
                    'prs_item_id' => $items[1]->id,
                    'canvasser_id' => $this->canvasser->id,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($prs->fresh()->status)->toBe('CANVASSING');
    expect($items[0]->fresh()->canvasser_id)->toBe($this->canvasser->id);
    expect($items[1]->fresh()->canvasser_id)->toBe($this->canvasser->id);
});

it('shows process for requested and edit canvasser for canvassing prs', function () {
    $requestedPrs = createRequestedPrsWithItems(1);
    $canvassingPrs = assignPrsToCanvassing(createRequestedPrsWithItems(1), $this->canvasser);

    $response = $this->actingAs($this->manager)
        ->get(route('prs.approval.index'));

    $response->assertSuccessful()
        ->assertSee('data-bs-target="#approve-modal-'.$requestedPrs->id.'"', false)
        ->assertSee('data-bs-target="#reassign-modal-'.$canvassingPrs->id.'"', false)
        ->assertSee('Edit Canvasser')
        ->assertDontSee('data-bs-target="#approve-modal-'.$canvassingPrs->id.'"', false);
});

it('rejects approve when prs is already canvassing', function () {
    $prs = assignPrsToCanvassing(createRequestedPrsWithItems(1), $this->canvasser);
    $item = $prs->items()->first();

    $this->actingAs($this->manager)
        ->from(route('prs.approval.index'))
        ->post(route('prs.approve', $prs), [
            'items' => [
                [
                    'prs_item_id' => $item->id,
                    'canvasser_id' => $this->replacementCanvasser->id,
                ],
            ],
        ])
        ->assertRedirect(route('prs.approval.index'))
        ->assertSessionHasErrors('message');

    expect($item->fresh()->canvasser_id)->toBe($this->canvasser->id);
});

it('reassigns canvasser while keeping quotes and selection', function () {
    $prs = assignPrsToCanvassing(createRequestedPrsWithItems(1), $this->canvasser);
    $item = $prs->items()->first();
    $assignedAt = now()->subHour();
    $item->update(['assigned_canvasser_at' => $assignedAt]);

    $quote = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $item->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1500,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $item->update([
        'selected_canvassing_item_id' => $quote->id,
        'selection_reason' => 'Best offer',
    ]);

    $this->actingAs($this->manager)
        ->post(route('prs.reassign', $prs), [
            'items' => [
                [
                    'prs_item_id' => $item->id,
                    'canvasser_id' => $this->replacementCanvasser->id,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $item->refresh();

    expect($prs->fresh()->status)->toBe('CANVASSING')
        ->and($item->canvasser_id)->toBe($this->replacementCanvasser->id)
        ->and($item->selected_canvassing_item_id)->toBe($quote->id)
        ->and($item->selection_reason)->toBe('Best offer')
        ->and($item->canvassingItems()->count())->toBe(1)
        ->and($item->assigned_canvasser_at?->greaterThan($assignedAt))->toBeTrue();

    $log = PrsLog::query()
        ->where('prs_id', $prs->id)
        ->where('action', 'REASSIGN_CANVASSER')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['changes'][0]['previous_canvasser_id'])->toBe($this->canvasser->id)
        ->and($log->meta['changes'][0]['new_canvasser_id'])->toBe($this->replacementCanvasser->id);
});

it('blocks reassignment when the item already has a purchase order', function () {
    $prs = assignPrsToCanvassing(createRequestedPrsWithItems(1), $this->canvasser);
    $item = $prs->items()->first();

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->manager->id,
        'status' => 'DRAFT',
        'po_number' => 'PO-ASN-LOCKED',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $item->update(['purchase_order_id' => $purchaseOrder->id]);

    $this->actingAs($this->manager)
        ->from(route('prs.approval.index'))
        ->post(route('prs.reassign', $prs), [
            'items' => [
                [
                    'prs_item_id' => $item->id,
                    'canvasser_id' => $this->replacementCanvasser->id,
                ],
            ],
        ])
        ->assertRedirect(route('prs.approval.index'))
        ->assertSessionHasErrors('items');

    expect($item->fresh()->canvasser_id)->toBe($this->canvasser->id);
});

it('reassigns only open items on a partially ordered prs', function () {
    $prs = assignPrsToCanvassing(createRequestedPrsWithItems(2), $this->canvasser);
    $items = $prs->items()->orderBy('id')->get();

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'created_by' => $this->manager->id,
        'status' => 'DRAFT',
        'po_number' => 'PO-ASN-PARTIAL',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $items[0]->update(['purchase_order_id' => $purchaseOrder->id]);

    $this->actingAs($this->manager)
        ->post(route('prs.reassign', $prs), [
            'items' => [
                [
                    'prs_item_id' => $items[1]->id,
                    'canvasser_id' => $this->replacementCanvasser->id,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($items[0]->fresh()->canvasser_id)->toBe($this->canvasser->id)
        ->and($items[1]->fresh()->canvasser_id)->toBe($this->replacementCanvasser->id);
});
