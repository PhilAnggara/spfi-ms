<?php

use App\Models\Department;
use App\Models\Supplier;
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
