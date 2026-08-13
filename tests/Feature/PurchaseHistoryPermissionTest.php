<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchase History Dept',
        'code' => 'PH01',
        'alias' => 'PH',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Spare Parts',
        'code' => 'SPR',
    ]);

    $this->item = Item::query()->create([
        'name' => 'History Product',
        'code' => 'HISTPRD1',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 1,
        'is_active' => true,
    ]);

    $seedUser = User::query()->create([
        'name' => 'History Seed User',
        'username' => 'history-seed',
        'email' => 'history-seed@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->supplier = Supplier::query()->create([
        'name' => 'History Supplier',
        'code' => 'HIST-SUP',
        'address' => 'Test Address',
        'phone' => '0811111111',
        'email' => 'hist-sup@example.test',
        'contact_person' => 'Contact Person',
        'created_by' => $seedUser->id,
    ]);
});

function createHistoryUser(string $username, ?string $spatieRole = null): User
{
    $user = User::query()->create([
        'name' => "User {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($spatieRole) {
        $user->assignRole($spatieRole);
    }

    return $user;
}

it('seeds view-purchase-history for purchasing it and administrator but not im', function () {
    expect(Permission::findByName('view-purchase-history'))->not->toBeNull();

    expect(Role::findByName('administrator')->hasPermissionTo('view-purchase-history'))->toBeTrue();
    expect(Role::findByName('purchasing-manager')->hasPermissionTo('view-purchase-history'))->toBeTrue();
    expect(Role::findByName('purchasing-staff')->hasPermissionTo('view-purchase-history'))->toBeTrue();
    expect(Role::findByName('it-manager')->hasPermissionTo('view-purchase-history'))->toBeTrue();
    expect(Role::findByName('it-staff')->hasPermissionTo('view-purchase-history'))->toBeTrue();

    expect(Role::findByName('im-manager')->hasPermissionTo('view-purchase-history'))->toBeFalse();
    expect(Role::findByName('im-supervisor')->hasPermissionTo('view-purchase-history'))->toBeFalse();
    expect(Role::findByName('im-staff')->hasPermissionTo('view-purchase-history'))->toBeFalse();
});

it('forbids im manager from product purchase history but allows product list without price columns', function () {
    $user = createHistoryUser('im-hist-product', 'im-manager');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-purchase-history="0"', false)
        ->assertSee('Stock')
        ->assertDontSee('Avg Unit Price')
        ->assertDontSee('product-purchase-history-modal', false);

    $this->actingAs($user)
        ->get(route('product.purchase-history', $this->item))
        ->assertForbidden();

    $response = $this->actingAs($user)
        ->getJson(route('product.datatables'))
        ->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $this->item->id);
    expect($row)->not->toBeNull()
        ->and($row)->toHaveKey('stock_on_hand')
        ->and($row['stock_on_hand'])->toEqual(1)
        ->and($row)->not->toHaveKey('avg_unit_price')
        ->and($row)->not->toHaveKey('avg_price_currency');
});

it('allows purchasing staff to open product purchase history and see avg price in list payload', function () {
    $user = createHistoryUser('pur-hist-product', 'purchasing-staff');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-purchase-history="1"', false)
        ->assertSee('Stock')
        ->assertSee('Avg Unit Price')
        ->assertSee('product-purchase-history-modal', false);

    $this->actingAs($user)
        ->get(route('product.purchase-history', $this->item))
        ->assertSuccessful();

    $response = $this->actingAs($user)
        ->getJson(route('product.datatables'))
        ->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $this->item->id);
    expect($row)->not->toBeNull()
        ->and($row)->toHaveKey('avg_unit_price');
});

it('forbids users with only view-suppliers from supplier purchase history and po columns', function () {
    $user = createHistoryUser('view-sup-only');
    $user->givePermissionTo(['view-suppliers']);

    $this->actingAs($user)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-purchase-history="0"', false)
        ->assertDontSee('PO Count')
        ->assertDontSee('Total Amount')
        ->assertDontSee('Last PO Date')
        ->assertSee('Phone')
        ->assertSee('Contact Person')
        ->assertSee('Email')
        ->assertDontSee('>PO History</label>', false);

    $this->actingAs($user)
        ->get(route('supplier.purchase-history', $this->supplier))
        ->assertForbidden();

    $response = $this->actingAs($user)
        ->getJson(route('supplier.datatables'))
        ->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $this->supplier->id);
    expect($row)->not->toBeNull()
        ->and($row)->not->toHaveKey('po_count')
        ->and($row)->not->toHaveKey('primary_total_amount')
        ->and($row)->not->toHaveKey('last_po_date')
        ->and($row)->not->toHaveKey('purchase_totals')
        ->and($row['phone'] ?? null)->toBe('0811111111');
});

it('allows it staff to open supplier purchase history and see po stats', function () {
    $user = createHistoryUser('it-hist-supplier', 'it-staff');

    $this->actingAs($user)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-view-purchase-history="1"', false)
        ->assertSee('PO Count')
        ->assertSee('Total Amount')
        ->assertSee('Last PO Date');

    $this->actingAs($user)
        ->get(route('supplier.purchase-history', $this->supplier))
        ->assertSuccessful();

    $response = $this->actingAs($user)
        ->getJson(route('supplier.datatables'))
        ->assertSuccessful();

    $row = collect($response->json('data'))->firstWhere('id', $this->supplier->id);
    expect($row)->not->toBeNull()
        ->and($row)->toHaveKey('po_count')
        ->and($row)->toHaveKey('purchase_totals');
});
