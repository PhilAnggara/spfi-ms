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

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7200',
        'alias' => 'PUR',
    ]);

    $this->canvasser = User::query()->create([
        'name' => 'PO Preview Canvasser',
        'username' => 'po-preview-canvasser',
        'email' => 'po-preview-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    Currency::query()->create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
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

    $item = Item::query()->create([
        'name' => 'Preview Precision Item',
        'code' => 'PREV-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-PREV-001',
        'name' => 'Preview Supplier',
        'created_by' => $this->canvasser->id,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => '71010000777',
        'user_id' => $this->canvasser->id,
        'department_id' => $department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Preview layout PRS',
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
        'unit_price' => 1234.56789,
        'lead_time_days' => 7,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $prsItem->update([
        'selected_canvassing_item_id' => $canvassing->id,
    ]);

    $this->supplier = $supplier;
    $this->prsItem = $prsItem;
});

it('renders po preview with five decimal precision and spacious line layout', function () {
    $response = $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.preview'), [
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'prs_item_id' => $this->prsItem->id,
                    'quantity' => 2,
                    'unit_price' => 1234.56789,
                    'notes' => null,
                    'checked' => '1',
                ],
            ],
        ]);

    $response->assertSuccessful();
    $response->assertSee('po-preview-page', false);
    $response->assertSee('po-preview-line', false);
    $response->assertSee('po-preview-line-primary', false);
    $response->assertSee('po-preview-field--price', false);
    $response->assertSee('step="0.00001"', false);
    $response->assertSee('value="1234.56789"', false);
    $response->assertSee('Line Items');
    $response->assertDontSee('bulk-discount-input', false);
    $response->assertDontSee('Apply to all items');

    $css = file_get_contents(public_path('assets/css/purchase-orders-modern.css'));

    expect($css)
        ->toContain('.po-preview-page')
        ->toContain('#eef8fa')
        ->toContain('border-left: 3px solid #1f7a8c')
        ->toContain('.po-preview-line:nth-child(even)')
        ->toContain('background: #eef8fa')
        ->toContain('.po-preview-bulk-rates');
});

it('shows bulk rate inputs on po preview when there are multiple line items', function () {
    $unit = UnitOfMeasure::query()->firstOrFail();
    $category = ItemCategory::query()->firstOrFail();
    $department = Department::query()->firstOrFail();

    $secondItem = Item::query()->create([
        'name' => 'Preview Precision Item B',
        'code' => 'PREV-ITEM-002',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $prs = Prs::query()->create([
        'prs_number' => '71010000778',
        'user_id' => $this->canvasser->id,
        'department_id' => $department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Preview multi-item PRS',
        'status' => 'CANVASSING',
    ]);

    $prsItemB = PrsItem::query()->create([
        'prs_id' => $prs->id,
        'item_id' => $secondItem->id,
        'quantity' => 1,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $canvassingB = PrsCanvassingItem::query()->create([
        'prs_id' => $prs->id,
        'prs_item_id' => $prsItemB->id,
        'supplier_id' => $this->supplier->id,
        'unit_price' => 500.12345,
        'lead_time_days' => 5,
        'term_of_payment_type' => 'cash',
        'canvased_by' => $this->canvasser->id,
    ]);

    $prsItemB->update([
        'selected_canvassing_item_id' => $canvassingB->id,
    ]);

    $response = $this->actingAs($this->canvasser)
        ->post(route('purchase-orders.preview'), [
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'prs_item_id' => $this->prsItem->id,
                    'quantity' => 2,
                    'unit_price' => 1234.56789,
                    'notes' => null,
                    'checked' => '1',
                ],
                [
                    'prs_item_id' => $prsItemB->id,
                    'quantity' => 1,
                    'unit_price' => 500.12345,
                    'notes' => null,
                    'checked' => '1',
                ],
            ],
        ]);

    $response->assertSuccessful();
    $response->assertSee('Apply to all items');
    $response->assertSee('bulk-discount-input', false);
    $response->assertSee('bulk-ppn-input', false);
    $response->assertSee('bulk-pph-input', false);
    $response->assertSee('po-preview-bulk-rates', false);
});

it('formats po decimals with five places', function () {
    expect(format_po_decimal(1234.567891, true))->toBe('1234.56789')
        ->and(format_po_decimal(1234.5))->toBe('1.234,50000');
});
