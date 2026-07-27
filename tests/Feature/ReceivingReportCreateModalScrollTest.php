<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7100',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'RR Modal User',
        'username' => 'rr-modal-user',
        'email' => 'rr-modal-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');
});

it('renders create rr modal as scrollable with sticky footer save button', function () {
    $response = $this->actingAs($this->user)
        ->get(route('receiving-reports.index'));

    $response->assertSuccessful();
    $response->assertSee('id="create-rr-modal"', false);
    $response->assertSee('modal-dialog modal-xl modal-dialog-scrollable', false);
    $response->assertSee('id="create-save-btn"', false);
    $response->assertSee('Save RR');
    $response->assertSee('assets/css/prs-modern.css', false);
});

it('includes css so create rr form body scrolls and footer stays visible', function () {
    $css = file_get_contents(public_path('assets/css/prs-modern.css'));

    expect($css)->toContain('#create-rr-modal .modal-content > form');
    expect($css)->toContain('#create-rr-modal .modal-body');
    expect($css)->toContain('overflow-y: auto');
    expect($css)->toContain('#create-rr-modal .modal-footer');
    expect($css)->toContain('flex-shrink: 0');
});
