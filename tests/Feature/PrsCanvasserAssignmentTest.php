<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
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
