<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Warehouse',
        'code' => '7042',
        'alias' => 'WH',
    ]);

    $this->user = User::query()->create([
        'name' => 'SWS Number Creator',
        'username' => 'sws-number-creator',
        'email' => 'sws-number-creator@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Supplies',
        'code' => 'SUP',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Warehouse Supply',
        'code' => 'SWS-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 20,
        'is_active' => true,
    ]);
});

function validStoreWithdrawalPayload(Department $department, Item $item): array
{
    return [
        'department_id' => $department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'SWS numbering test',
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
            ],
        ],
    ];
}

function insertStoreWithdrawalRecord(array $attributes): int
{
    $now = now();

    return DB::table('store_withdrawals')->insertGetId(array_merge([
        'sws_date' => $now->toDateString(),
        'department_id' => null,
        'department_code' => '',
        'type' => 'normal',
        'info' => 'Legacy SWS',
        'created_by' => null,
        'updated_by' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], $attributes));
}

it('starts at 0000001 when no matching department code prefix exists', function () {
    $this->actingAs($this->user)
        ->post(route('stores-withdrawals.store'), validStoreWithdrawalPayload($this->department, $this->item))
        ->assertRedirect(route('stores-withdrawals.index'));

    $created = DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->sws_number)->toBe('70420000001');
});

it('continues past soft-deleted sws numbers with the same department code prefix', function () {
    $deletedId = insertStoreWithdrawalRecord([
        'sws_number' => '70420000077',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'deleted_at' => now(),
    ]);

    expect($deletedId)->toBeInt();

    $this->actingAs($this->user)
        ->post(route('stores-withdrawals.store'), validStoreWithdrawalPayload($this->department, $this->item))
        ->assertRedirect(route('stores-withdrawals.index'));

    $created = DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->whereNull('deleted_at')
        ->latest('id')
        ->first();

    expect($created->sws_number)->toBe('70420000078');
});

it('ignores oversized numeric suffixes that would overflow sql int', function () {
    insertStoreWithdrawalRecord([
        'sws_number' => '704200021520000001',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    insertStoreWithdrawalRecord([
        'sws_number' => '70420000012',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('stores-withdrawals.store'), validStoreWithdrawalPayload($this->department, $this->item))
        ->assertRedirect(route('stores-withdrawals.index'));

    $created = DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->whereNull('deleted_at')
        ->latest('id')
        ->first();

    expect($created->sws_number)->toBe('70420000013');
});
