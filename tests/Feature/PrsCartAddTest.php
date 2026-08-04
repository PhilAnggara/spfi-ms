<?php

use App\Livewire\PrsItem;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Engineering',
        'code' => '7101',
        'alias' => 'ENG',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Cart Creator',
        'username' => 'prs-cart-creator',
        'email' => 'prs-cart-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Spare Parts',
        'code' => 'SPR',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Bolt',
        'code' => 'BLT-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 25,
        'is_active' => true,
    ]);
});

it('renders the create page with livewire cart host and add buttons', function () {
    $html = $this->actingAs($this->creator)
        ->get(route('prs.create'))
        ->assertSuccessful()
        ->assertSeeLivewire('prs-item')
        ->assertSee('prs-item-add', false)
        ->assertSee('prs-cart-component', false)
        ->getContent();

    expect($html)->toMatch('/wire:id="[^"]+"/');
    expect($html)->toContain('id="prs-cart-component"');
});

it('adds an item to the cart from the catalog', function () {
    Livewire::actingAs($this->creator)
        ->test(PrsItem::class)
        ->call('addFromCatalog', $this->item->id, 2.5)
        ->assertSet('prsItems.0.item_id', $this->item->id)
        ->assertSet('prsItems.0.quantity', 2.5)
        ->assertDispatched('prs-cart-count');
});

it('adds an item when the catalog browser event is dispatched', function () {
    Livewire::actingAs($this->creator)
        ->test(PrsItem::class)
        ->dispatch('prs-catalog-add', itemId: $this->item->id, quantity: 3)
        ->assertSet('prsItems.0.item_id', $this->item->id)
        ->assertSet('prsItems.0.quantity', 3)
        ->assertCount('prsItems', 1);
});

it('updates quantity when the same catalog item is added again', function () {
    Livewire::actingAs($this->creator)
        ->test(PrsItem::class)
        ->call('addFromCatalog', $this->item->id, 1)
        ->call('addFromCatalog', $this->item->id, 4)
        ->assertCount('prsItems', 1)
        ->assertSet('prsItems.0.quantity', 4);
});
