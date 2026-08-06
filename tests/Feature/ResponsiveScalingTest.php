<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('loads spfi scaling stylesheets on the dashboard', function () {
    $department = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $user = User::query()->create([
        'name' => 'Scaling Test User',
        'username' => 'scale-test-'.uniqid(),
        'email' => 'scale-test-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole('administrator');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('spfi-tokens.css', false)
        ->assertSee('spfi-scale.css', false)
        ->assertSee('spfi-layout.css', false)
        ->assertSee('spfi-components.css', false)
        ->assertSee('id="sidebar"', false)
        ->assertDontSee('set-font-size.js', false);
});

it('loads spfi scaling stylesheets on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('spfi-tokens.css', false)
        ->assertSee('spfi-scale.css', false)
        ->assertDontSee('set-font-size.js', false);
});
