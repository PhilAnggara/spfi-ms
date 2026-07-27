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
        'name' => 'Canvasser Staff',
        'username' => 'canvass-decimal-canvasser',
        'email' => 'canvass-decimal-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->manager = User::query()->create([
        'name' => 'Purchasing Manager',
        'username' => 'canvass-decimal-manager',
        'email' => 'canvass-decimal-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'canvass-decimal-creator',
        'email' => 'canvass-decimal-creator@example.test',
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
        'name' => 'Decimal Price Item',
        'code' => 'DEC-PRICE',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-DEC-001',
        'name' => 'Decimal Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '71019990001',
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Decimal price canvassing PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 4,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
    ]);
});

it('allows saving canvassing unit prices with five decimal places', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('canvassing.store', $this->prsItem), [
            'suppliers' => [
                [
                    'supplier_id' => $this->supplier->id,
                    'unit_price' => '1234.56789',
                    'lead_time_days' => 3,
                    'notes' => 'Five decimal quote',
                ],
            ],
        ]);

    $response->assertRedirect();

    $quote = PrsCanvassingItem::query()
        ->where('prs_item_id', $this->prsItem->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($quote)->not->toBeNull()
        ->and($quote->unit_price)->toBe('1234.56789');
});

it('rejects canvassing unit prices with more than five decimal places', function () {
    $response = $this->actingAs($this->canvasser)
        ->from(route('canvassing.show', $this->prsItem))
        ->post(route('canvassing.store', $this->prsItem), [
            'suppliers' => [
                [
                    'supplier_id' => $this->supplier->id,
                    'unit_price' => '1234.567891',
                ],
            ],
        ]);

    $response->assertRedirect(route('canvassing.show', $this->prsItem))
        ->assertSessionHasErrors('suppliers.0.unit_price');

    expect(PrsCanvassingItem::query()->where('prs_item_id', $this->prsItem->id)->count())->toBe(0);
});

it('renders canvassing detail unit price input with five decimal step and value', function () {
    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1234.56789,
        'canvased_by' => $this->canvasser->id,
    ]);

    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.show', $this->prsItem));

    $response->assertOk()
        ->assertSee('step="0.00001"', false)
        ->assertSee('value="1234.56789"', false);
});
