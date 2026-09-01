<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Direct Perm Dept',
        'code' => 'DPD',
        'alias' => 'DPD',
    ]);

    $this->admin = User::query()->create([
        'name' => 'Admin Direct Perm',
        'username' => 'admin-direct-perm',
        'email' => 'admin-direct-perm@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->admin->assignRole('administrator');

    $this->userWithoutDirect = User::query()->create([
        'name' => 'No Direct User',
        'username' => 'no-direct-user',
        'email' => 'no-direct-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->userWithoutDirect->assignRole('purchasing-staff');

    $this->userWithOneDirect = User::query()->create([
        'name' => 'One Direct User',
        'username' => 'one-direct-user',
        'email' => 'one-direct-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->userWithOneDirect->givePermissionTo('view-all-prs');

    $this->userWithTwoDirect = User::query()->create([
        'name' => 'Two Direct User',
        'username' => 'two-direct-user',
        'email' => 'two-direct-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->userWithTwoDirect->givePermissionTo(['view-all-prs', 'delete-rr']);
});

it('does not show a direct permissions label for users without direct permissions', function () {
    $this->actingAs($this->admin)
        ->get(route('user.index'))
        ->assertSuccessful()
        ->assertDontSee('No direct permissions', false)
        ->assertDontSee('0 direct permissions', false)
        ->assertSee('no-direct-user', false);
});

it('shows singular direct permission label when user has one direct permission', function () {
    $this->actingAs($this->admin)
        ->get(route('user.index'))
        ->assertSuccessful()
        ->assertSee('1 direct permission', false)
        ->assertSee('one-direct-user', false)
        ->assertDontSee('1 direct permissions', false);
});

it('shows plural direct permissions label when user has multiple direct permissions', function () {
    $this->actingAs($this->admin)
        ->get(route('user.index'))
        ->assertSuccessful()
        ->assertSee('2 direct permissions', false)
        ->assertSee('two-direct-user', false);
});
