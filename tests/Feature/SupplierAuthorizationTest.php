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
        'name' => 'Supplier Seed User',
        'username' => 'supplier-seed',
        'email' => 'supplier-seed@example.test',
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

    $this->supplierPayload = [
        'code' => 'SUP-NEW01',
        'name' => 'New Supplier',
        'address' => 'New Address',
        'phone' => '08123456789',
        'fax' => null,
        'email' => 'supplier@example.test',
        'contact_person' => 'Jane Doe',
        'remarks' => null,
    ];
});

function createSupplierUser(string $role): User
{
    $user = User::query()->create([
        'name' => "Supplier {$role} User",
        'username' => "supplier-{$role}",
        'email' => "supplier-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

it('allows purchasing staff to create and update suppliers but not delete', function () {
    $user = createSupplierUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-manage="1"', false)
        ->assertSee('data-can-delete="0"', false);

    $this->actingAs($user)
        ->post(route('supplier.store'), $this->supplierPayload)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Supplier::query()->where('code', 'SUP-NEW01')->exists())->toBeTrue();

    $this->actingAs($user)
        ->put(route('supplier.update', $this->supplier), [
            'code' => 'SUP-EXIST',
            'name' => 'Updated by Staff',
            'address' => 'Updated Address',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->supplier->fresh()->name)->toBe('Updated by Staff');

    $this->actingAs($user)
        ->delete(route('supplier.destroy', $this->supplier))
        ->assertForbidden();
});

it('allows purchasing manager to create and update suppliers but not delete', function () {
    $user = createSupplierUser('purchasing-manager');

    $this->actingAs($user)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-manage="1"', false)
        ->assertSee('data-can-delete="0"', false);

    $this->actingAs($user)
        ->post(route('supplier.store'), array_merge($this->supplierPayload, [
            'code' => 'SUP-MGR01',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->put(route('supplier.update', $this->supplier), [
            'code' => 'SUP-EXIST',
            'name' => 'Updated by Manager',
            'address' => 'Updated Address',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->supplier->fresh()->name)->toBe('Updated by Manager');

    $this->actingAs($user)
        ->delete(route('supplier.destroy', $this->supplier))
        ->assertForbidden();
});

it('allows administrator full supplier crud', function () {
    $user = createSupplierUser('administrator');

    $this->actingAs($user)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-manage="1"', false)
        ->assertSee('data-can-delete="1"', false);

    $this->actingAs($user)
        ->post(route('supplier.store'), array_merge($this->supplierPayload, [
            'code' => 'SUP-ADM01',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->put(route('supplier.update', $this->supplier), [
            'code' => 'SUP-EXIST',
            'name' => 'Updated by Admin',
            'address' => 'Updated Address',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->supplier->fresh()->name)->toBe('Updated by Admin');

    $this->actingAs($user)
        ->delete(route('supplier.destroy', $this->supplier))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Supplier::query()->whereKey($this->supplier->id)->exists())->toBeFalse();
});

it('forbids engineering manager from creating and updating suppliers', function () {
    $user = createSupplierUser('engineering-manager');

    $this->actingAs($user)
        ->post(route('supplier.store'), $this->supplierPayload)
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('supplier.update', $this->supplier), [
            'code' => 'SUP-EXIST',
            'name' => 'Should Not Update',
            'address' => 'Should Not Update',
        ])
        ->assertForbidden();
});
