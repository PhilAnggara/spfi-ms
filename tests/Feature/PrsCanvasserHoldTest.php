<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'prs-creator-hold',
        'email' => 'prs-creator-hold@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'prs-canvasser',
        'email' => 'prs-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->otherCanvasser = User::query()->create([
        'name' => 'Other Canvasser',
        'username' => 'prs-canvasser-other',
        'email' => 'prs-canvasser-other@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->otherCanvasser->assignRole('purchasing-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Test Item Alpha',
        'code' => 'ITM-ALPHA',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->secondItem = Item::query()->create([
        'name' => 'Test Item Beta',
        'code' => 'ITM-BETA',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 5,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-001',
        'name' => 'Supplier One',
        'created_by' => $this->canvasser->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '71010000001',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Canvassing PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 4,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    $this->secondPrsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->secondItem->id,
        'quantity' => 2,
        'canvasser_id' => $this->otherCanvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    $this->canvassingItem = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItem->update([
        'selected_canvassing_item_id' => $this->canvassingItem->id,
    ]);
});

it('allows assigned canvasser to hold prs for quantity revision', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('canvassing.hold', $this->prsItem), [
            'message' => 'Requested quantity does not match specification.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->prs->refresh();
    expect($this->prs->status)->toBe('CANVASSER_HOLD');
    expect($this->prs->logs()->where('action', 'CANVASSER_HOLD')->exists())->toBeTrue();
});

it('forbids non assigned canvasser from holding prs', function () {
    $response = $this->actingAs($this->otherCanvasser)
        ->post(route('canvassing.hold', $this->prsItem), [
            'message' => 'Should not work.',
        ]);

    $response->assertForbidden();

    $this->prs->refresh();
    expect($this->prs->status)->toBe('CANVASSING');
});

it('allows creator to revise quantities and returns prs to canvassing', function () {
    $this->prs->update(['status' => 'CANVASSER_HOLD']);

    $response = $this->actingAs($this->creator)
        ->put(route('prs.update', $this->prs), [
            'prsItems' => [
                [
                    'prs_item_id' => $this->prsItem->id,
                    'item_id' => $this->item->id,
                    'quantity' => 6,
                ],
                [
                    'prs_item_id' => $this->secondPrsItem->id,
                    'item_id' => $this->secondItem->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->prs->refresh();
    $this->prsItem->refresh();

    expect($this->prs->status)->toBe('CANVASSING');
    expect($this->prsItem->quantity)->toBe(6);
    expect($this->prsItem->canvasser_id)->toBe($this->canvasser->id);
    expect($this->prsItem->selected_canvassing_item_id)->toBe($this->canvassingItem->id);
    expect($this->prs->logs()->where('action', 'QUANTITY_REVISED')->exists())->toBeTrue();
});

it('rejects quantity revision when item list changes', function () {
    $this->prs->update(['status' => 'CANVASSER_HOLD']);

    $response = $this->actingAs($this->creator)
        ->put(route('prs.update', $this->prs), [
            'prsItems' => [
                [
                    'prs_item_id' => $this->prsItem->id,
                    'item_id' => $this->secondItem->id,
                    'quantity' => 6,
                ],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('prsItems');

    $this->prs->refresh();
    expect($this->prs->status)->toBe('CANVASSER_HOLD');
});

it('forbids full prs edit while in canvassing status', function () {
    $response = $this->actingAs($this->creator)
        ->put(route('prs.update', $this->prs), [
            'department_id' => $this->department->id,
            'date_needed' => now()->addDays(14)->toDateString(),
            'is_capex' => '0',
            'remarks' => 'Attempted edit',
            'prsItems' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 3,
                ],
            ],
        ]);

    $response->assertForbidden();
});
