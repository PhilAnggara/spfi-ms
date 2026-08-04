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

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7211',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'Draft Unchecked Canvasser',
        'username' => 'po-draft-unchecked',
        'email' => 'po-draft-unchecked@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $item = Item::query()->create([
        'name' => 'Draft Unchecked Item',
        'code' => 'DRAFT-UC-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-DRAFT-UC',
        'name' => 'Draft Unchecked Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => '72110000999',
        'user_id' => $this->canvasser->id,
        'department_id' => $department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Draft unchecked PRS',
        'status' => 'CANVASSING',
    ]);

    $prsItem = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassing = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $prsItem->id,
        'supplier_id' => $supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $prsItem->update([
        'selected_canvassing_item_id' => $canvassing->id,
    ]);
});

it('renders draft purchase order items unchecked by default', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('purchase-orders.draft'));

    $response->assertSuccessful();
    $response->assertSee('class="form-check-input item-checkbox"', false);
    $response->assertDontSee('class="form-check-input item-checkbox" checked', false);
    $response->assertDontSee('class="form-check-input item-checkbox" data-accounting-category="non_capex" checked', false);
    $response->assertSee('class="item-checked" value="0"', false);
    $response->assertSee('select-all', false);
});
