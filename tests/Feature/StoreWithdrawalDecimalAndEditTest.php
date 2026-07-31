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
        'code' => '7037',
        'alias' => 'PROD',
    ]);

    $this->user = User::query()->create([
        'name' => 'SWS Decimal User',
        'username' => 'sws.decimal',
        'email' => 'sws.decimal@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Kilogram',
        'code' => 'KG',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Raw Materials',
        'code' => 'RAW',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Decimal Withdrawal Item',
        'code' => 'DEC-SWS-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);

    $this->secondItem = Item::query()->create([
        'name' => 'Second Withdrawal Item',
        'code' => 'DEC-SWS-002',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'stock_on_hand' => 20,
        'is_active' => true,
    ]);
});

it('creates a stores withdrawal with decimal quantity', function () {
    $response = $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Decimal create',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 0.5,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHasNoErrors();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    expect((float) DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->value('quantity'))->toBe(0.5);
});

it('allows normal stores withdrawal when quantity matches fractional stock_on_hand', function () {
    $this->item->update(['stock_on_hand' => 25.1]);

    $response = $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Fractional SOH create',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 25.1,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHasNoErrors();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    expect((float) DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->value('quantity'))->toBe(25.1);
});

it('shows the edit page with existing cart items', function () {
    $create = $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1.25,
            ],
        ],
    ]);

    $create->assertRedirect(route('stores-withdrawals.index'));

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.edit', $storeWithdrawalId));

    $response->assertSuccessful();
    $response->assertSee('Edit Stores Withdrawal');
    $response->assertSee('sws-edit-page', false);
    $response->assertSee('DEC-SWS-001');
});

it('updates stores withdrawal lines and decimal quantities from the edit page payload', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $response = $this->actingAs($this->user)->put(route('stores-withdrawals.update', $storeWithdrawalId), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'info' => 'Updated decimals',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1.5,
            ],
            [
                'item_id' => $this->secondItem->id,
                'quantity' => 0.75,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHasNoErrors();

    $activeItems = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->orderBy('item_id')
        ->get(['item_id', 'quantity']);

    expect($activeItems)->toHaveCount(2)
        ->and((float) $activeItems->firstWhere('item_id', $this->item->id)->quantity)->toBe(1.5)
        ->and((float) $activeItems->firstWhere('item_id', $this->secondItem->id)->quantity)->toBe(0.75);

    expect(DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->where('item_id', $this->item->id)
        ->whereNull('deleted_at')
        ->count())->toBe(1);
});

it('redirects edit when all lines are fully transferred', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'NORMAL',
        'items' => [
            [
                'item_id' => $this->item->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')
        ->where('created_by', $this->user->id)
        ->latest('id')
        ->value('id');

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->value('id');

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-LOCK-001',
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
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 1,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.edit', $storeWithdrawalId));

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHas('error');
});
