<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->itStaff = User::query()->create([
        'name' => 'IT Staff Master Access',
        'username' => 'it-staff-master',
        'email' => 'it-staff-master@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->itStaff->assignRole('it-staff');
});

it('allows it-staff to open master pages and see the master menu', function () {
    $this->actingAs($this->itStaff)
        ->get(route('user.index'))
        ->assertSuccessful()
        ->assertSee('Master');

    $this->actingAs($this->itStaff)
        ->get(route('employees.index'))
        ->assertSuccessful();

    $this->actingAs($this->itStaff)
        ->get(route('product.index'))
        ->assertSuccessful()
        ->assertSee('data-can-create="1"', false)
        ->assertSee('data-can-manage="1"', false);

    $this->actingAs($this->itStaff)
        ->get(route('supplier.index'))
        ->assertSuccessful()
        ->assertSee('data-can-manage="1"', false)
        ->assertSee('data-can-delete="1"', false);

    $this->actingAs($this->itStaff)
        ->get(route('product-category.index'))
        ->assertSuccessful();

    $this->actingAs($this->itStaff)
        ->get(route('accounting.groupings.index'))
        ->assertSuccessful();
});
