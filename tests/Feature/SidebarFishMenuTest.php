<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the fish system link on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(config('services.fish_system.url'), false)
        ->assertSee('Separate module · opens in a new tab', false)
        ->assertSee('Fish Module', false);
});

it('shows the fish system link in the sidebar', function () {
    $department = Department::query()->create([
        'name' => 'General',
        'code' => '1000',
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

    $this->actingAs($user);

    $html = view('includes.sidebar')->render();

    expect($html)
        ->toContain(config('services.fish_system.url'))
        ->toContain('target="_blank"')
        ->toContain('>Fish</span>');
});
