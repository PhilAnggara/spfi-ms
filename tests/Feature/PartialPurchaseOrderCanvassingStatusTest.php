<?php

use App\Models\Currency;
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
        'code' => '7301',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'Partial PO Canvasser',
        'username' => 'partial-po-canvasser',
        'email' => 'partial-po-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->currency = Currency::query()->create([
        'name' => 'Indonesian Rupiah',
        'code' => 'IDR',
        'symbol' => 'Rp',
        'created_by' => $this->canvasser->id,
    ]);

    $this->supplier = Supplier::query()->create([
        'code' => 'SUP-PARTIAL-001',
        'name' => 'Partial PO Supplier',
        'created_by' => $this->canvasser->id,
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
        'name' => 'Partial Item A',
        'code' => 'PARTIAL-A',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Partial Item B',
        'code' => 'PARTIAL-B',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '73010000999',
        'user_id' => $this->canvasser->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Partial PO canvassing PRS',
        'status' => 'CANVASSING',
    ]);

    $this->prsItemA = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemA->id,
        'quantity' => 2,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->prsItemB = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->itemB->id,
        'quantity' => 3,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassingA = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemA->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 1000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $canvassingB = PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItemB->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 2000,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $this->prsItemA->update(['selected_canvassing_item_id' => $canvassingA->id]);
    $this->prsItemB->update(['selected_canvassing_item_id' => $canvassingB->id]);
});

it('keeps prs status canvassing when only some items get a purchase order', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'remark_type' => 'Normal',
            'remark_text' => null,
            'term_of_payment_type' => 'cash',
            'term_of_payment' => 'COD',
            'term_of_delivery' => '7 days',
            'items' => [
                [
                    'prs_item_id' => $this->prsItemA->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($this->prs->fresh()->status)->toBe('CANVASSING')
        ->and($this->prsItemA->fresh()->purchase_order_id)->not->toBeNull()
        ->and($this->prsItemB->fresh()->purchase_order_id)->toBeNull();

    $this->actingAs($this->canvasser)
        ->get(route('canvassing.index'))
        ->assertSuccessful()
        ->assertSee('PARTIAL-B')
        ->assertDontSee('PARTIAL-A');
});

it('sets prs status to po_created when all open items have purchase orders', function () {
    $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'remark_type' => 'Normal',
            'remark_text' => null,
            'term_of_payment_type' => 'cash',
            'term_of_payment' => 'COD',
            'term_of_delivery' => '7 days',
            'items' => [
                [
                    'prs_item_id' => $this->prsItemA->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                ],
                [
                    'prs_item_id' => $this->prsItemB->id,
                    'quantity' => 3,
                    'unit_price' => 2000,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->prs->fresh()->status)->toBe('PO_CREATED')
        ->and($this->prsItemA->fresh()->purchase_order_id)->not->toBeNull()
        ->and($this->prsItemB->fresh()->purchase_order_id)->not->toBeNull();
});

it('shows assigned open items on canvassing index even when prs status is po_created', function () {
    $this->prs->update(['status' => 'PO_CREATED']);

    $response = $this->actingAs($this->canvasser)
        ->get(route('canvassing.index'));

    $response->assertSuccessful();
    $response->assertSee('PARTIAL-A');
    $response->assertSee('PARTIAL-B');
    $response->assertSee('73010000999');
});
