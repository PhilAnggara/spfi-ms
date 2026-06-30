<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'prs-edit-creator',
        'email' => 'prs-edit-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->otherUser = User::query()->create([
        'name' => 'Other User',
        'username' => 'prs-edit-other',
        'email' => 'prs-edit-other@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Test Item',
        'code' => 'ITM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => 'PRS-IT-2026-EDIT1',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Editable PRS',
        'status' => 'REQUESTED',
    ]);

    PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
    ]);
});

it('allows the creator to open the prs edit page', function () {
    $this->actingAs($this->creator)
        ->get(route('prs.edit', $this->prs))
        ->assertSuccessful()
        ->assertSee('Edit Purchase Requisition Slip')
        ->assertSee('prs-item-grid', false)
        ->assertSeeLivewire('prs-item');
});

it('returns catalog json from the prs edit page', function () {
    $response = $this->actingAs($this->creator)
        ->getJson(route('prs.edit', $this->prs).'?search=ITM');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'code', 'stock_on_hand', 'unit', 'category'],
            ],
            'meta' => ['current_page', 'last_page', 'total', 'per_page'],
        ]);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('meta.per_page'))->toBe(36);
});

it('forbids other users from opening the prs edit page', function () {
    $this->actingAs($this->otherUser)
        ->get(route('prs.edit', $this->prs))
        ->assertForbidden();
});

it('forbids editing a prs that is not in an editable status', function () {
    $this->prs->update(['status' => 'CANVASSING']);

    $this->actingAs($this->creator)
        ->get(route('prs.edit', $this->prs))
        ->assertForbidden();
});

it('does not render full edit modals on the prs index page', function () {
    $response = $this->actingAs($this->creator)
        ->get(route('prs.index'));

    $response->assertSuccessful();
    expect(substr_count($response->getContent(), 'mode="form"'))->toBe(0);
    expect(substr_count($response->getContent(), 'prs-item-edit-'))->toBe(0);
});
