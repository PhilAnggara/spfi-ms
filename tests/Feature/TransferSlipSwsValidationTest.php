<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory SWS Valid',
        'code' => '7202',
        'alias' => 'INV-SWSV',
    ]);

    $this->user = User::query()->create([
        'name' => 'TS SWS Validation User',
        'username' => 'ts-sws-validation-user',
        'email' => 'ts-sws-validation-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-SWSV',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables SWS Valid',
        'code' => 'CNS-SWSV',
    ]);

    $this->item = Item::query()->create([
        'name' => 'SWS Validation Transfer Item',
        'code' => 'TS-SWSV-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 100,
        'start_balance' => 100,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);
});

/**
 * @return array{0: int, 1: int, 2: string, 3: \Illuminate\Support\Carbon}
 */
function createSwsForValidation(object $context, string $swsNumber = 'DEP0009001'): array
{
    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => $swsNumber,
        'sws_date' => $now->toDateString(),
        'department_id' => $context->department->id,
        'department_code' => $context->department->code,
        'type' => 'normal',
        'info' => 'SWS for validation tests',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $context->item->id,
        'product_code' => $context->item->code,
        'quantity' => 10,
        'uom' => 'PCS',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber, $now];
}

it('creates a transfer slip when posted sws_number has trailing whitespace', function () {
    [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber, $now] = createSwsForValidation($this, 'DEP0009001');

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => $swsNumber.' ',
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    expect(DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->exists())->toBeTrue();
});

it('rejects create when sws_number does not match the selected store withdrawal', function () {
    [$storeWithdrawalId, $storeWithdrawalItemId, , $now] = createSwsForValidation($this, 'DEP0009002');

    DB::table('store_withdrawals')->insert([
        'sws_number' => 'DEP0009999',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'normal',
        'info' => 'Other SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => 'DEP0009999',
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasErrors([
        'sws_number' => 'Selected SWS is no longer valid. Please load the SWS again.',
    ]);

    expect(DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->exists())->toBeFalse();
});

it('loads sws by number when the query has trailing whitespace or different case', function () {
    [, , $swsNumber] = createSwsForValidation($this, 'DEP0009003');

    $response = $this->actingAs($this->user)
        ->getJson(route('transfer-slips.sws-by-number', [
            'sws_number' => '  dep0009003  ',
        ]));

    $response->assertOk()
        ->assertJsonPath('store_withdrawal.sws_number', $swsNumber);
});

it('updates a transfer slip when posted sws_number has trailing whitespace', function () {
    [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber, $now] = createSwsForValidation($this, 'DEP0009004');

    $create = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => $swsNumber,
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 3,
                ],
            ],
        ]);

    $create->assertRedirect(route('transfer-slips.index'));
    $create->assertSessionHasNoErrors();

    $transferSlipId = (int) DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->value('id');

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->put(route('transfer-slips.update', $transferSlipId), [
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'remarks' => 'Updated with padded sws',
            'sws_number' => $swsNumber."\u{00A0}",
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $storeWithdrawalItemId,
                    'item_id' => $this->item->id,
                    'quantity' => 4,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    expect((float) DB::table('transfer_slip_items')
        ->where('transfer_slip_id', $transferSlipId)
        ->whereNull('deleted_at')
        ->value('quantity'))->toBe(4.0);
});
