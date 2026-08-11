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
        'name' => 'Supplier Datatable User',
        'username' => 'supplier-datatable',
        'email' => 'supplier-datatable@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $this->user->assignRole('purchasing-staff');

    foreach (range(1, 15) as $number) {
        Supplier::query()->create([
            'name' => "Supplier {$number}",
            'code' => sprintf('SUP-%03d', $number),
            'address' => "Address {$number}",
            'created_by' => $this->user->id,
        ]);
    }
});

it('returns paginated supplier datatable rows', function () {
    $response = $this->actingAs($this->user)->getJson(route('supplier.datatables', [
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

it('filters supplier datatable rows by keyword', function () {
    $response = $this->actingAs($this->user)->getJson(route('supplier.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'keyword' => 'SUP-001',
        'order' => [
            ['column' => 1, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.code', 'SUP-001');
});

it('orders supplier datatable rows by name ascending', function () {
    $response = $this->actingAs($this->user)->getJson(route('supplier.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 5,
        'order' => [
            ['column' => 2, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Supplier 1');
});

it('orders supplier datatable rows by total amount', function () {
    $currency = Currency::query()->create([
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Frozen Goods',
        'code' => 'FRZ',
    ]);

    $item = Item::query()->create([
        'name' => 'Supplier Amount Item',
        'code' => 'ITM-SUP-AMT',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Raw Material',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $lowSupplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
    $highSupplier = Supplier::query()->where('code', 'SUP-002')->firstOrFail();

    $lowPo = PurchaseOrder::query()->create([
        'supplier_id' => $lowSupplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-SUP-LOW',
    ]);

    $highPo = PurchaseOrder::query()->create([
        'supplier_id' => $highSupplier->id,
        'currency_id' => $currency->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-SUP-HIGH',
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $lowPo->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 10,
        'line_subtotal' => 20,
        'total' => 20,
    ]);

    PurchaseOrderItem::query()->create([
        'purchase_order_id' => $highPo->id,
        'item_id' => $item->id,
        'quantity' => 5,
        'unit_price' => 20,
        'line_subtotal' => 100,
        'total' => 100,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('supplier.datatables', [
        'draw' => 1,
        'start' => 0,
        'length' => 15,
        'order' => [
            ['column' => 5, 'dir' => 'asc'],
        ],
    ]));

    $response->assertSuccessful();

    $amountRows = collect($response->json('data'))
        ->filter(fn (array $row) => $row['primary_total_amount'] !== null)
        ->values();

    expect($amountRows)->toHaveCount(2)
        ->and($amountRows[0]['code'])->toBe('SUP-001')
        ->and((float) $amountRows[0]['primary_total_amount'])->toBe(20.0)
        ->and($amountRows[1]['code'])->toBe('SUP-002')
        ->and((float) $amountRows[1]['primary_total_amount'])->toBe(100.0);
});

it('passes sort filter to the supplier index page', function () {
    $response = $this->actingAs($this->user)->get(route('supplier.index', [
        'sort' => 'total_amount_desc',
    ]));

    $response->assertSuccessful();
    expect($response->viewData('filters')['sort'])->toBe('total_amount_desc');
});

it('falls back to name_asc for invalid supplier sort filter', function () {
    $response = $this->actingAs($this->user)->get(route('supplier.index', [
        'sort' => 'invalid_sort',
    ]));

    $response->assertSuccessful();
    expect($response->viewData('filters')['sort'])->toBe('name_asc');
});
