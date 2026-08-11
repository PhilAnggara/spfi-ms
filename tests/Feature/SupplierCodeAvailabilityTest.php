<?php

use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $seedUser = User::query()->create([
        'name' => 'Supplier Code Seed User',
        'username' => 'supplier-code-seed',
        'email' => 'supplier-code-seed@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->supplier = Supplier::query()->create([
        'name' => 'Existing Supplier',
        'code' => 'SUP-EXIST',
        'address' => 'Existing Address',
        'created_by' => $seedUser->id,
    ]);
});

function createSupplierCodeUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Supplier Code {$role} User",
        'username' => "supplier-code-{$role}",
        'email' => "supplier-code-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

it('returns available true for unused supplier code', function () {
    $user = createSupplierCodeUser('purchasing-staff');

    $this->actingAs($user)
        ->getJson(route('supplier.check-code', ['code' => 'SUP-NEW01']))
        ->assertSuccessful()
        ->assertJson([
            'available' => true,
            'message' => 'Code is available.',
        ]);
});

it('returns available false for duplicate supplier code', function () {
    $user = createSupplierCodeUser('purchasing-staff');

    $this->actingAs($user)
        ->getJson(route('supplier.check-code', ['code' => 'SUP-EXIST']))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'message' => 'This code has already been used.',
        ]);
});

it('returns available true when editing supplier with same code and ignore id', function () {
    $user = createSupplierCodeUser('purchasing-manager');

    $this->actingAs($user)
        ->getJson(route('supplier.check-code', [
            'code' => 'SUP-EXIST',
            'ignore_id' => $this->supplier->id,
        ]))
        ->assertSuccessful()
        ->assertJson([
            'available' => true,
            'message' => 'Code is available.',
        ]);
});

it('returns available false when editing supplier with code owned by another supplier', function () {
    $user = createSupplierCodeUser('purchasing-manager');

    $otherSupplier = Supplier::query()->create([
        'name' => 'Other Supplier',
        'code' => 'SUP-OTHER',
        'address' => 'Other Address',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('supplier.check-code', [
            'code' => 'SUP-OTHER',
            'ignore_id' => $this->supplier->id,
        ]))
        ->assertSuccessful()
        ->assertJson([
            'available' => false,
            'message' => 'This code has already been used.',
        ]);

    expect($otherSupplier)->not->toBeNull();
});

it('requires supplier code when checking availability', function () {
    $user = createSupplierCodeUser('purchasing-staff');

    $this->actingAs($user)
        ->getJson(route('supplier.check-code'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('forbids unauthorized users from checking supplier code availability', function () {
    $user = createSupplierCodeUser('engineering-manager');

    $this->actingAs($user)
        ->getJson(route('supplier.check-code', ['code' => 'SUP-NEW01']))
        ->assertForbidden();
});
