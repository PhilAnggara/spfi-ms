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
});

function createProductCodeUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Product Code {$role} User",
        'username' => "product-code-{$role}",
        'email' => "product-code-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

it('returns available true for unused product code', function () {
    $user = createProductCodeUser('engineering-manager');

    $this->actingAs($user)
        ->getJson(route('product.check-code', ['code' => 'NEWCODE1']))
        ->assertSuccessful()
        ->assertJson([
            'available' => true,
            'message' => 'Code is available.',
        ]);
});

it('returns available false for duplicate product code', function () {
    $user = createProductCodeUser('engineering-manager');

    $this->actingAs($user)
        ->getJson(route('product.check-code', ['code' => 'TSTPRD01']))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'message' => 'This code has already been used.',
        ]);
});

it('returns available true when editing product with same code and ignore id', function () {
    $user = createProductCodeUser('im-manager');

    $this->actingAs($user)
        ->getJson(route('product.check-code', [
            'code' => 'TSTPRD01',
            'ignore_id' => $this->item->id,
        ]))
        ->assertSuccessful()
        ->assertJson([
            'available' => true,
            'message' => 'Code is available.',
        ]);
});

it('returns available false when editing product with code owned by another item', function () {
    $user = createProductCodeUser('im-manager');

    $otherItem = Item::query()->create([
        'name' => 'Other Product',
        'code' => 'OTHER001',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson(route('product.check-code', [
            'code' => 'OTHER001',
            'ignore_id' => $this->item->id,
        ]))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'message' => 'This code has already been used.',
        ]);

    expect($otherItem)->not->toBeNull();
});

it('rejects invalid product code format', function () {
    $user = createProductCodeUser('engineering-manager');

    $this->actingAs($user)
        ->getJson(route('product.check-code', ['code' => 'BAD-CODE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('rejects product code longer than eight characters', function () {
    $user = createProductCodeUser('engineering-manager');

    $this->actingAs($user)
        ->getJson(route('product.check-code', ['code' => 'TOOLONGCODE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('forbids unauthorized users from checking product code availability', function () {
    $user = createProductCodeUser('purchasing-staff');

    $this->actingAs($user)
        ->getJson(route('product.check-code', ['code' => 'NEWCODE1']))
        ->assertForbidden();
});
