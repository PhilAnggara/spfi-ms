<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
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

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Frozen Goods',
        'code' => 'FRZ',
    ]);

    foreach (range(1, 15) as $number) {
        Item::query()->create([
            'name' => "Product {$number}",
            'code' => sprintf('PRD-%03d', $number),
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
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
