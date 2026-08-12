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
        ->assertSee('burger-btn', false)
        ->assertSee('aria-label="Toggle sidebar"', false)
        ->assertSee('<button type="button" class="burger-btn', false)
        ->assertDontSee('<a href="#" class="burger-btn', false)
        ->assertDontSee('data-bs-display="static"', false)
        ->assertDontSee('set-font-size.js', false);
});

it('floors form control font size to avoid mobile input zoom', function () {
    $componentsCss = file_get_contents(public_path('assets/styles/spfi-components.css'));

    expect($componentsCss)
        ->toContain('--spfi-input-font-size: 16px')
        ->toContain('@media (max-width: 1600px)')
        ->toContain('.form-control-sm')
        ->toContain('.form-control.form-control-xl')
        ->toContain('.choices__input');
});

it('keeps notification dropdown inside the mobile viewport', function () {
    $componentsCss = file_get_contents(public_path('assets/styles/spfi-components.css'));

    expect($componentsCss)
        ->toContain('.notification-dropdown')
        ->toContain('@media (max-width: 575.98px)')
        ->toContain('position: fixed')
        ->toContain('left: 0.5rem')
        ->toContain('right: 0.5rem');
});

it('keeps the mobile sidebar above page loading overlays', function () {
    $layoutCss = file_get_contents(public_path('assets/styles/spfi-layout.css'));

    expect($layoutCss)
        ->toContain('@media screen and (max-width: 1199px)')
        ->toContain('z-index: 1040')
        ->toContain('z-index: 1045')
        ->toContain('.sidebar-backdrop');
});

it('gives the burger button a usable touch target', function () {
    $mainCss = file_get_contents(public_path('assets/styles/main.css'));

    expect($mainCss)
        ->toContain('.burger-btn')
        ->toContain('min-width: 2.75rem')
        ->toContain('min-height: 2.75rem');
});

it('loads spfi scaling stylesheets on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('spfi-tokens.css', false)
        ->assertSee('spfi-scale.css', false)
        ->assertDontSee('set-font-size.js', false);
});

it('loads spfi scaling stylesheets on the 404 error page', function () {
    $this->get('/this-route-does-not-exist-for-scaling-test')
        ->assertNotFound()
        ->assertSee('spfi-tokens.css', false)
        ->assertSee('spfi-scale.css', false)
        ->assertSee('spfi-error.css', false)
        ->assertDontSee('set-font-size.js', false);
});
