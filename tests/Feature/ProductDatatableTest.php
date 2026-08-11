<?php

use App\Models\Currency;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'Product Datatable User',
        'username' => 'product-datatable',
        'email' => 'product-datatable@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'Frozen Goods',
        'code' => 'FRZ',
    ]);

    foreach (range(1, 15) as $number) {
        Item::query()->create([
            'name' => "Product {$number}",
            'code' => sprintf('PRD-%03d', $number),
            'unit_of_measure_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'type' => 'Raw Material',
            'stock_on_hand' => $number,
            'is_active' => true,
        ]);
    }
});

it('returns paginated product datatable rows', function () {
    $response = $this->actingAs($this->user)->getJson(route('product.datatables', [
        'draw' => 2,
        'start' => 10,
        'length' => 5,
        'order' => [
            ['column' => 0, 'dir' => 'desc'],
        ],
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('draw', 2)
        ->assertJsonPath('recordsTotal', 15)
        ->assertJsonPath('recordsFiltered', 15);

    expect($response->json('data'))->toHaveCount(5);
});

it('filters product datatable rows by keyword', function () {
    $response = $this->actingAs($this->user)->getJson(route('product.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'keyword' => 'PRD-001',
        'order' => [
            ['column' => 1, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'PRD-001');
});

it('orders product datatable rows by name ascending', function () {
    $response = $this->actingAs($this->user)->getJson(route('product.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 5,
        'order' => [
            ['column' => 2, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Product 1');
});

it('orders product datatable rows by avg unit price', function () {
    $currency = Currency::query()->create([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'created_by' => $this->user->id,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Avg Price Supplier',
        'code' => 'SUP-AVG-001',
        'created_by' => $this->user->id,
    ]);

    $cheapItem = Item::query()->where('code', 'PRD-001')->firstOrFail();
    $expensiveItem = Item::query()->where('code', 'PRD-002')->firstOrFail();

    $cheapPo = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-AVG-CHEAP',
    ]);

    $expensivePo = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-AVG-EXP',
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $cheapPo->id,
        'item_id' => $cheapItem->id,
        'quantity' => 10,
        'unit_price' => 5,
        'line_subtotal' => 50,
        'total' => 50,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $expensivePo->id,
        'item_id' => $expensiveItem->id,
        'quantity' => 10,
        'unit_price' => 50,
        'line_subtotal' => 500,
        'total' => 500,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('product.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 15,
        'order' => [
            ['column' => 6, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful();

    $pricedRows = collect($response->json('data'))
        ->filter(fn (array $row) => $row['avg_unit_price'] !== null)
        ->values();

    expect($pricedRows)->toHaveCount(2)
        ->and($pricedRows[0]['code'])->toBe('PRD-001')
        ->and((float) $pricedRows[0]['avg_unit_price'])->toBe(5.0)
        ->and($pricedRows[1]['code'])->toBe('PRD-002')
        ->and((float) $pricedRows[1]['avg_unit_price'])->toBe(50.0);
});

it('passes sort filter to the product index page', function () {
    $response = $this->actingAs($this->user)->get(route('product.index', [
        'sort' => 'avg_unit_price_desc',
    ]));

    $response->assertSuccessful();
    expect($response->viewData('filters')['sort'])->toBe('avg_unit_price_desc');
});

it('falls back to name_asc for invalid product sort filter', function () {
    $response = $this->actingAs($this->user)->get(route('product.index', [
        'sort' => 'invalid_sort',
    ]));

    $response->assertSuccessful();
    expect($response->viewData('filters')['sort'])->toBe('name_asc');
});
