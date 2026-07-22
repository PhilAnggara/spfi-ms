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

    $this->chargedDepartment = Department::query()->create([
        'name' => 'Finance',
        'code' => '1005',
        'alias' => 'FIN',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'prs-creator',
        'email' => 'prs-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->otherUser = User::query()->create([
        'name' => 'Other User',
        'username' => 'prs-other',
        'email' => 'prs-other@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->chargedDepartmentUser = User::query()->create([
        'name' => 'Finance User',
        'username' => 'prs-finance',
        'email' => 'prs-finance@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->chargedDepartment->id,
        'role' => 'Staff',
    ]);

    $this->admin = User::query()->create([
        'name' => 'Administrator',
        'username' => 'prs-admin',
        'email' => 'prs-admin@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Manager',
    ]);
    $this->admin->assignRole('administrator');

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
        'prs_number' => 'PRS-IT-2026-0001',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Test PRS',
        'status' => 'ON_HOLD',
    ]);

    PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
    ]);
});

function validPrsUpdatePayload(Department $department, Item $item): array
{
    return [
        'department_id' => $department->id,
        'date_needed' => now()->addDays(14)->toDateString(),
        'is_capex' => '0',
        'remarks' => 'Updated remarks',
        'prsItems' => [
            [
                'item_id' => $item->id,
                'quantity' => 3,
            ],
        ],
    ];
}

it('allows the creator to update a prs', function () {
    $response = $this->actingAs($this->creator)
        ->put(route('prs.update', $this->prs), validPrsUpdatePayload($this->department, $this->item));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->prs->refresh();
    expect($this->prs->status)->toBe('REVISED');
    expect($this->prs->remarks)->toBe('Updated remarks');
});

it('allows an administrator to update a prs they did not create', function () {
    $response = $this->actingAs($this->admin)
        ->put(route('prs.update', $this->prs), validPrsUpdatePayload($this->department, $this->item));

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('forbids other users from updating a prs', function () {
    $response = $this->actingAs($this->otherUser)
        ->put(route('prs.update', $this->prs), validPrsUpdatePayload($this->department, $this->item));

    $response->assertForbidden();
});

it('allows the creator to delete a prs', function () {
    $response = $this->actingAs($this->creator)
        ->delete(route('prs.destroy', $this->prs));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(Prs::query()->find($this->prs->id))->toBeNull();
});

it('allows an administrator to delete a prs they did not create', function () {
    $response = $this->actingAs($this->admin)
        ->delete(route('prs.destroy', $this->prs));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(Prs::query()->find($this->prs->id))->toBeNull();
});

it('forbids other users from deleting a prs', function () {
    $response = $this->actingAs($this->otherUser)
        ->delete(route('prs.destroy', $this->prs));

    $response->assertForbidden();
    expect(Prs::query()->find($this->prs->id))->not->toBeNull();
});

it('lets department peers view a prs charged to another department on the index', function () {
    $this->prs->update([
        'department_id' => $this->chargedDepartment->id,
        'prs_number' => 'PRS-FIN-2026-0001',
    ]);

    $this->actingAs($this->otherUser)
        ->get(route('prs.index'))
        ->assertSuccessful()
        ->assertSee('PRS-FIN-2026-0001', false)
        ->assertDontSee(route('prs.edit', $this->prs), false);
});

it('hides a prs from users who only share the charged-to department', function () {
    $this->prs->update([
        'department_id' => $this->chargedDepartment->id,
        'prs_number' => 'PRS-FIN-2026-0002',
    ]);

    $this->actingAs($this->chargedDepartmentUser)
        ->get(route('prs.index'))
        ->assertSuccessful()
        ->assertDontSee('PRS-FIN-2026-0002', false);
});

it('lets a department peer stream the print pdf without changing on-hold status', function () {
    $response = $this->actingAs($this->otherUser)
        ->get(route('prs.print', $this->prs->id));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $this->prs->refresh();
    expect($this->prs->status)->toBe('ON_HOLD');
});

it('forbids print access for users outside the creator department', function () {
    $this->actingAs($this->chargedDepartmentUser)
        ->get(route('prs.print', $this->prs->id))
        ->assertForbidden();
});

it('lets the creator print and move an on-hold prs back to requested', function () {
    $response = $this->actingAs($this->creator)
        ->get(route('prs.print', $this->prs->id));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $this->prs->refresh();
    expect($this->prs->status)->toBe('REQUESTED');
});
