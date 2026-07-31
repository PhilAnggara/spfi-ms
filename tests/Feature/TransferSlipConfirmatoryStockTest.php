<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory',
        'code' => '7200',
        'alias' => 'INV-CONF',
    ]);

    $this->user = User::query()->create([
        'name' => 'TS Confirmatory User',
        'username' => 'ts-confirmatory-user',
        'email' => 'ts-confirmatory-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-CONF',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables',
        'code' => 'CNS-CONF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Confirmatory Transfer Item',
        'code' => 'TS-CONF-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 0,
        'start_balance' => 0,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);
});

it('allows transfer slip issue from confirmatory sws when stock is insufficient', function () {
    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-CONF-TS-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'confirmatory',
        'info' => 'Confirmatory zero-stock SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 5,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => 'SWS-CONF-TS-001',
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    $transferSlip = DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->first();

    expect($transferSlip)->not->toBeNull();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(-5.0);

    $balance = StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $transferSlip->id)
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->qty_out1)->toBe(5.0)
        ->and((float) $balance->end)->toBe(-5.0);
});

it('rejects transfer slip issue from normal sws when stock is insufficient', function () {
    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-NORMAL-TS-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'normal',
        'info' => 'Normal zero-stock SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 5,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => 'SWS-NORMAL-TS-001',
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasErrors('items');

    expect(DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->exists())->toBeFalse();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(0.0);
});
