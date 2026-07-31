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
        'name' => 'Production',
        'code' => '7038',
        'alias' => 'PROD2',
    ]);

    $this->user = User::query()->create([
        'name' => 'SWS Partial Edit User',
        'username' => 'sws.partial.edit',
        'email' => 'sws.partial.edit@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-PART',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Supplies',
        'code' => 'SUP-PART',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'Partial Item A',
        'code' => 'PART-A-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Partial Item B',
        'code' => 'PART-B-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);
});

it('allows editing stores withdrawal when only some items are transferred', function () {
    $create = $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 10],
            ['item_id' => $this->itemB->id, 'quantity' => 8],
        ],
    ]);

    $create->assertRedirect(route('stores-withdrawals.index'));

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $itemALine = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->where('item_id', $this->itemA->id)
        ->whereNull('deleted_at')
        ->first();

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-PARTIAL-001',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $itemALine->id,
        'item_id' => $this->itemA->id,
        'product_code' => $this->itemA->code,
        'quantity' => 10,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $editPage = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.edit', $storeWithdrawalId));

    $editPage->assertSuccessful();
    $editPage->assertSee('sws-edit-page', false);

    $update = $this->actingAs($this->user)->put(route('stores-withdrawals.update', $storeWithdrawalId), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Updated open line',
        'items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 10],
            ['item_id' => $this->itemB->id, 'quantity' => 5],
        ],
    ]);

    $update->assertRedirect(route('stores-withdrawals.index'));
    $update->assertSessionHasNoErrors();

    expect((float) DB::table('store_withdrawal_items')
        ->where('id', $itemALine->id)
        ->whereNull('deleted_at')
        ->value('quantity'))->toBe(10.0);

    expect((float) DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->where('item_id', $this->itemB->id)
        ->whereNull('deleted_at')
        ->value('quantity'))->toBe(5.0);
});

it('rejects removing a transferred line and quantity below transferred amount', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 10],
            ['item_id' => $this->itemB->id, 'quantity' => 8],
        ],
    ]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $itemALine = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->where('item_id', $this->itemA->id)
        ->whereNull('deleted_at')
        ->first();

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-PARTIAL-002',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $itemALine->id,
        'item_id' => $this->itemA->id,
        'product_code' => $this->itemA->code,
        'quantity' => 4,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $removeResponse = $this->actingAs($this->user)
        ->from(route('stores-withdrawals.edit', $storeWithdrawalId))
        ->put(route('stores-withdrawals.update', $storeWithdrawalId), [
            'department_id' => $this->department->id,
            'sws_date' => now()->toDateString(),
            'type' => 'NORMAL',
            'items' => [
                ['item_id' => $this->itemB->id, 'quantity' => 8],
            ],
        ]);

    $removeResponse->assertRedirect(route('stores-withdrawals.edit', $storeWithdrawalId));
    $removeResponse->assertSessionHasErrors('items');

    $belowTransferred = $this->actingAs($this->user)
        ->from(route('stores-withdrawals.edit', $storeWithdrawalId))
        ->put(route('stores-withdrawals.update', $storeWithdrawalId), [
            'department_id' => $this->department->id,
            'sws_date' => now()->toDateString(),
            'type' => 'NORMAL',
            'items' => [
                ['item_id' => $this->itemA->id, 'quantity' => 3],
                ['item_id' => $this->itemB->id, 'quantity' => 8],
            ],
        ]);

    $belowTransferred->assertRedirect(route('stores-withdrawals.edit', $storeWithdrawalId));
    $belowTransferred->assertSessionHasErrors('items');
});

it('locks edit when every stores withdrawal line is fully transferred', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 5],
            ['item_id' => $this->itemB->id, 'quantity' => 5],
        ],
    ]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $lines = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->get();

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-PARTIAL-003',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($lines as $line) {
        DB::table('transfer_slip_items')->insert([
            'transfer_slip_id' => $transferSlipId,
            'store_withdrawal_item_id' => $line->id,
            'item_id' => $line->item_id,
            'product_code' => $line->product_code,
            'quantity' => $line->quantity,
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $editPage = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.edit', $storeWithdrawalId));

    $editPage->assertRedirect(route('stores-withdrawals.index'));
    $editPage->assertSessionHas('error');
});
