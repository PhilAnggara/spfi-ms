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
        'name' => 'Inventory TS Status',
        'code' => '7211',
        'alias' => 'INV-TSS',
    ]);

    $this->user = User::query()->create([
        'name' => 'SWS TS Status User',
        'username' => 'sws-ts-status-user',
        'email' => 'sws-ts-status-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-TSS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables TS Status',
        'code' => 'CNS-TSS',
    ]);

    $this->item = Item::query()->create([
        'name' => 'TS Status Item',
        'code' => 'TSS-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);
});

/**
 * @return array{0: int, 1: int, 2: string}
 */
function createSwsForTsStatus(object $context, string $swsNumber, float $quantity = 10): array
{
    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => $swsNumber,
        'sws_date' => $now->toDateString(),
        'department_id' => $context->department->id,
        'department_code' => $context->department->code,
        'type' => 'normal',
        'info' => 'SWS for TS status tests',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $context->item->id,
        'product_code' => $context->item->code,
        'quantity' => $quantity,
        'uom' => 'PCS',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber];
}

function createTransferForSwsItem(object $context, int $storeWithdrawalId, int $storeWithdrawalItemId, float $quantity, string $tsNumber): void
{
    $now = now();

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => $tsNumber,
        'ts_date' => $now->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'created_by' => $context->user->id,
        'updated_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $context->item->id,
        'product_code' => $context->item->code,
        'quantity' => $quantity,
        'created_by' => $context->user->id,
        'updated_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('shows not taken status when sws has no transfer slip', function () {
    [, , $swsNumber] = createSwsForTsStatus($this, 'DEP0009101');

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful()
        ->assertSee($swsNumber)
        ->assertSee('Not Taken')
        ->assertSee('TS Status');
});

it('shows partial status when sws is only partly transferred', function () {
    [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber] = createSwsForTsStatus($this, 'DEP0009102', 10);
    createTransferForSwsItem($this, $storeWithdrawalId, $storeWithdrawalItemId, 4, 'TS-STATUS-PARTIAL');

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful()
        ->assertSee($swsNumber)
        ->assertSee('Partial');
});

it('shows taken status when sws is fully transferred', function () {
    [$storeWithdrawalId, $storeWithdrawalItemId, $swsNumber] = createSwsForTsStatus($this, 'DEP0009103', 10);
    createTransferForSwsItem($this, $storeWithdrawalId, $storeWithdrawalItemId, 10, 'TS-STATUS-TAKEN');

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index'));

    $response->assertSuccessful()
        ->assertSee($swsNumber)
        ->assertSee('Taken');
});

it('filters stores withdrawals by ts status', function () {
    [, , $notTakenNumber] = createSwsForTsStatus($this, 'DEP0009110', 10);

    [$partialId, $partialItemId, $partialNumber] = createSwsForTsStatus($this, 'DEP0009111', 10);
    createTransferForSwsItem($this, $partialId, $partialItemId, 4, 'TS-FILTER-PARTIAL');

    [$takenId, $takenItemId, $takenNumber] = createSwsForTsStatus($this, 'DEP0009112', 10);
    createTransferForSwsItem($this, $takenId, $takenItemId, 10, 'TS-FILTER-TAKEN');

    $notTaken = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index', ['ts_status' => 'not_taken']));

    $notTaken->assertSuccessful()
        ->assertSee($notTakenNumber)
        ->assertDontSee($partialNumber)
        ->assertDontSee($takenNumber)
        ->assertSee('value="not_taken"', false)
        ->assertSee('selected', false);

    $partial = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index', ['ts_status' => 'partial']));

    $partial->assertSuccessful()
        ->assertSee($partialNumber)
        ->assertDontSee($notTakenNumber)
        ->assertDontSee($takenNumber);

    $taken = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.index', ['ts_status' => 'taken']));

    $taken->assertSuccessful()
        ->assertSee($takenNumber)
        ->assertDontSee($notTakenNumber)
        ->assertDontSee($partialNumber);
});
