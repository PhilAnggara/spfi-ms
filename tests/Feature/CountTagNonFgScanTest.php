<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042-CT',
        'alias' => 'IM-CT',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-CT',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Raw Materials',
        'code' => 'RM-CT',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Count Tag Scan Item',
        'code' => 'CTSCAN01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 12,
        'is_active' => true,
    ]);

    $this->inactiveItem = Item::query()->create([
        'name' => 'Inactive Scan Item',
        'code' => 'CTINACT1',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => false,
    ]);
});

function createCountTagUser(string $username, array $permissions = []): User
{
    $user = User::query()->create([
        'name' => "Count Tag {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('allows users with view permission to open the count tag list', function () {
    $user = createCountTagUser('ct-view', ['view-count-tag-non-fg']);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index'))
        ->assertSuccessful()
        ->assertSee('Count Tag Non-FG')
        ->assertSee('No count tags yet');
});

it('allows users with create permission to open the scan page', function () {
    $user = createCountTagUser('ct-scan', [
        'view-count-tag-non-fg',
        'create-count-tag-non-fg',
    ]);

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.scan'))
        ->assertSuccessful()
        ->assertSee('Scan Product QR')
        ->assertSee('Manual Lookup')
        ->assertSee('id="count-tag-entry-modal"', false)
        ->assertSee('Count Tag Entry')
        ->assertSee('id="count-tag-result-card"', false)
        ->assertSee('role="button"', false)
        ->assertSee('Tap to continue');
});

it('forbids users without permission from opening count tag pages', function () {
    $user = createCountTagUser('ct-denied');

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('count-tags.non-fg.scan'))
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', ['code' => 'CTSCAN01']))
        ->assertForbidden();
});

it('looks up an active product by code', function () {
    $user = createCountTagUser('ct-lookup', ['create-count-tag-non-fg']);

    $response = $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', ['code' => 'CTSCAN01']));

    $response->assertSuccessful()
        ->assertJsonPath('id', $this->item->id)
        ->assertJsonPath('code', 'CTSCAN01')
        ->assertJsonPath('name', 'Count Tag Scan Item')
        ->assertJsonPath('unit_name', 'Pieces')
        ->assertJsonPath('category_name', 'Raw Materials');
});

it('returns not found for unknown or inactive product codes', function () {
    $user = createCountTagUser('ct-miss', ['create-count-tag-non-fg']);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', ['code' => 'UNKNOWN1']))
        ->assertNotFound()
        ->assertJsonPath('message', 'Product not found for code: UNKNOWN1');

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup', ['code' => 'CTINACT1']))
        ->assertNotFound();
});

it('validates lookup code is required', function () {
    $user = createCountTagUser('ct-valid', ['create-count-tag-non-fg']);

    $this->actingAs($user)
        ->getJson(route('count-tags.non-fg.lookup'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('seeds count tag permissions for administrator only by default', function () {
    $admin = createCountTagUser('ct-admin');
    $admin->assignRole('administrator');

    $staff = createCountTagUser('ct-staff');
    $staff->assignRole('im-staff');

    expect($admin->can('view-count-tag-non-fg'))->toBeTrue()
        ->and($admin->can('create-count-tag-non-fg'))->toBeTrue()
        ->and($staff->can('view-count-tag-non-fg'))->toBeFalse()
        ->and($staff->can('create-count-tag-non-fg'))->toBeFalse();
});
