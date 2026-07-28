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
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'Bulk Report Canvasser',
        'username' => 'bulk-report-canvasser',
        'email' => 'bulk-report-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->otherCanvasser = User::query()->create([
        'name' => 'Other Canvasser',
        'username' => 'bulk-report-other-canvasser',
        'email' => 'bulk-report-other-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->otherCanvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'bulk-report-creator',
        'email' => 'bulk-report-creator@example.test',
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

    $this->itemA = Item::query()->create([
        'name' => 'Bulk Report Item A',
        'code' => 'BULK-A',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Bulk Report Item B',
        'code' => 'BULK-B',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-BULK-001',
        'name' => 'Bulk Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '71019990011',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Bulk canvassing report PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItemA = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 4,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    $this->prsItemB = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemB->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    $this->prsItemWithoutQuotes = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 1,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 100,
        'canvased_by' => $this->canvasser->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemB->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 150,
        'canvased_by' => $this->canvasser->id,
    ]);
});

it('streams a single canvassing report pdf for an item with quotes', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.report', $this->prsItemA));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('streams a bulk canvassing reports pdf for selected printable items', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.reports.print', [
            'prs_item_ids' => [$this->prsItemA->id, $this->prsItemB->id],
        ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('rejects bulk print when a selected item has no supplier quotes', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.reports.print', [
            'prs_item_ids' => [$this->prsItemA->id, $this->prsItemWithoutQuotes->id],
        ]));

    $response->assertRedirect(route('canvassing.index'));
    $response->assertSessionHasErrors('message');
});

it('rejects bulk print when a selected item belongs to another canvasser', function () {
    $foreignPrsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'canvasser_id' => $this->otherCanvasser->id,
        'assigned_canvasser_at' => now(),
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $foreignPrsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 200,
        'canvased_by' => $this->otherCanvasser->id,
    ]);

    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.reports.print', [
            'prs_item_ids' => [$this->prsItemA->id, $foreignPrsItem->id],
        ]));

    $response->assertRedirect(route('canvassing.index'));
    $response->assertSessionHasErrors('message');
});

it('shows selection controls on the canvassing index page', function () {
    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.index'));

    $response->assertOk();
    $response->assertSee('id="canvassing-print-selected-btn"', false);
    $response->assertSee('id="canvassing-select-'.$this->prsItemA->id.'"', false);
    $response->assertSee('id="canvassing-print-modal"', false);
});
