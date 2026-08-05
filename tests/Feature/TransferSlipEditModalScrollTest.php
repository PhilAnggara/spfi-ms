<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7203',
        'alias' => 'INV-TS-SCROLL',
    ]);

    $this->user = User::query()->create([
        'name' => 'TS Modal Scroll User',
        'username' => 'ts-modal-scroll-user',
        'email' => 'ts-modal-scroll-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('administrator');
});

it('renders transfer slips page with edit modal scroll stylesheet', function () {
    $response = $this->actingAs($this->user)
        ->get(route('transfer-slips.index'));

    $response->assertSuccessful();
    $response->assertSee('assets/css/modules/transfer-slips-index.css', false);
});

it('includes css so edit ts form body scrolls and footer stays visible', function () {
    $css = file_get_contents(public_path('assets/css/modules/transfer-slips-index.css'));

    expect($css)->toContain('[id^="ts-edit-modal-"] .modal-content > form');
    expect($css)->toContain('[id^="ts-edit-modal-"] .modal-body');
    expect($css)->toContain('overflow-y: auto');
    expect($css)->toContain('[id^="ts-edit-modal-"] .modal-footer');
    expect($css)->toContain('flex-shrink: 0');
});
