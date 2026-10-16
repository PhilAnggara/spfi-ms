<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('hides the production sidebar link without view-fish-system', function () {
    $department = Department::query()->create([
        'name' => 'General',
        'code' => '1000',
        'alias' => 'GEN',
    ]);

    $user = User::query()->create([
        'name' => 'No Fish Sidebar User',
        'username' => 'no-fish-sidebar',
        'email' => 'no-fish-sidebar@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->actingAs($user);

    $html = view('includes.sidebar')->render();

    expect($html)
        ->not->toContain(config('services.fish_system.url'))
        ->not->toContain('>Production</span>');
});

it('shows the production sidebar link with view-fish-system', function () {
    $department = Department::query()->create([
        'name' => 'General',
        'code' => '1001',
        'alias' => 'GEN',
    ]);

    $user = User::query()->create([
        'name' => 'Fish Sidebar User',
        'username' => 'fish-sidebar',
        'email' => 'fish-sidebar@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $user->givePermissionTo('view-fish-system');

    $this->actingAs($user);

    $html = view('includes.sidebar')->render();

    expect($html)
        ->toContain(config('services.fish_system.url'))
        ->toContain('target="_blank"')
        ->toContain('>Production</span>');
});
