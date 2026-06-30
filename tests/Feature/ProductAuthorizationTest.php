<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Engineering',
        'code' => '7046',
        'alias' => 'ENG',
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
        'name' => 'Test Product',
        'code' => 'TSTPRD01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 1,
        'is_active' => true,
    ]);

    $this->productPayload = [
        'code' => 'NEWPRD01',
        'name' => 'New Product',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
    ];
});

function createProductUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Product {$role} User",
        'username' => "product-{$role}",
        'email' => "product-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

it('allows engineering manager to view and create products', function () {
    $user = createProductUser('engineering-manager');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->post(route('product.store'), $this->productPayload)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Item::query()->where('code', 'NEWPRD01')->exists())->toBeTrue();
});

it('forbids engineering manager from updating and deleting products', function () {
    $user = createProductUser('engineering-manager');

    $this->actingAs($user)
        ->put(route('product.update', $this->item), [
            'code' => 'TSTPRD01',
            'name' => 'Updated Name',
            'unit_of_measure_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'type' => 'Raw Material',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('product.destroy', $this->item))
        ->assertForbidden();
});

it('allows im manager full product crud', function () {
    $user = createProductUser('im-manager');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->post(route('product.store'), $this->productPayload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->put(route('product.update', $this->item), [
            'code' => 'TSTPRD01',
            'name' => 'IM Updated Product',
            'unit_of_measure_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'type' => 'Raw Material',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->item->fresh()->name)->toBe('IM Updated Product');

    $this->actingAs($user)
        ->delete(route('product.destroy', $this->item))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Item::query()->whereKey($this->item->id)->exists())->toBeFalse();
});

it('allows im supervisor full product crud', function () {
    $user = createProductUser('im-supervisor');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->post(route('product.store'), array_merge($this->productPayload, [
            'code' => 'IMSUP01',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->put(route('product.update', $this->item), [
            'code' => 'TSTPRD01',
            'name' => 'Supervisor Updated Product',
            'unit_of_measure_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'type' => 'Raw Material',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->delete(route('product.destroy', $this->item))
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('allows purchasing staff read-only product access', function () {
    $user = createProductUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->post(route('product.store'), $this->productPayload)
        ->assertForbidden();
});

it('forbids im staff from accessing product master', function () {
    $user = createProductUser('im-staff');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertForbidden();
});
