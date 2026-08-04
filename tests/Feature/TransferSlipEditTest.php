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
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Edit',
        'code' => '7201',
        'alias' => 'INV-EDIT',
    ]);

    $this->user = User::query()->create([
        'name' => 'TS Edit User',
        'username' => 'ts-edit-user',
        'email' => 'ts-edit-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-EDIT',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Consumables Edit',
        'code' => 'CNS-EDIT',
    ]);

    $this->itemA = Item::query()->create([
        'name' => 'Edit Transfer Item A',
        'code' => 'TS-EDIT-A',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    $this->itemB = Item::query()->create([
        'name' => 'Edit Transfer Item B',
        'code' => 'TS-EDIT-B',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 100,
        'is_active' => true,
    ]);

    foreach ([$this->itemA, $this->itemB] as $item) {
        StockInventory::query()->create([
            'item_id' => $item->id,
            'product_code' => $item->code,
            'wh_code' => 'MAIN',
            'balance' => 100,
            'start_balance' => 100,
            'average_price' => 10,
            'is_active' => true,
            'is_delete' => false,
        ]);
    }
});

function createNormalSwsWithTwoItems(object $context, float $qtyA = 10, float $qtyB = 10): array
{
    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-EDIT-'.uniqid(),
        'sws_date' => $now->toDateString(),
        'department_id' => $context->department->id,
        'department_code' => $context->department->code,
        'type' => 'normal',
        'info' => 'Normal SWS for edit tests',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemA = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $context->itemA->id,
        'product_code' => $context->itemA->code,
        'quantity' => $qtyA,
        'uom' => 'PCS',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemB = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $context->itemB->id,
        'product_code' => $context->itemB->code,
        'quantity' => $qtyB,
        'uom' => 'PCS',
        'created_by' => $context->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [$storeWithdrawalId, $swsItemA, $swsItemB, $now];
}

function createTransferSlip(object $context, int $storeWithdrawalId, array $items, string $tsDate): int
{
    $beforeIds = DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->pluck('id')
        ->all();

    $response = test()->actingAs($context->user)
        ->from(route('transfer-slips.index'))
        ->post(route('transfer-slips.store'), [
            'ts_date' => $tsDate,
            'for_production' => '0',
            'remarks' => 'Initial TS',
            'sws_number' => DB::table('store_withdrawals')->where('id', $storeWithdrawalId)->value('sws_number'),
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => $items,
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    $newId = DB::table('transfer_slips')
        ->where('store_withdrawal_id', $storeWithdrawalId)
        ->whereNull('deleted_at')
        ->whereNotIn('id', $beforeIds)
        ->value('id');

    expect($newId)->not->toBeNull();

    return (int) $newId;
}

function netQtyOut1(int $transferSlipId): float
{
    return round((float) StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $transferSlipId)
        ->sum('qty_out1'), 5);
}

it('updates header and quantities and adjusts stock', function () {
    [$storeWithdrawalId, $swsItemA, $swsItemB, $now] = createNormalSwsWithTwoItems($this);

    $transferSlipId = createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 4,
        ],
        [
            'store_withdrawal_item_id' => $swsItemB,
            'item_id' => $this->itemB->id,
            'quantity' => 3,
        ],
    ], $now->toDateString());

    expect(netQtyOut1($transferSlipId))->toBe(7.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(96.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemB->id)->value('balance'))->toBe(97.0);

    $swsNumber = DB::table('store_withdrawals')->where('id', $storeWithdrawalId)->value('sws_number');
    $newDate = $now->copy()->addDay()->toDateString();

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->put(route('transfer-slips.update', $transferSlipId), [
            '_edit_transfer_slip_id' => $transferSlipId,
            'ts_date' => $newDate,
            'for_production' => '1',
            'remarks' => 'Updated TS',
            'sws_number' => $swsNumber,
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $swsItemA,
                    'item_id' => $this->itemA->id,
                    'quantity' => 6,
                ],
                [
                    'store_withdrawal_item_id' => $swsItemB,
                    'item_id' => $this->itemB->id,
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    $transferSlip = DB::table('transfer_slips')->where('id', $transferSlipId)->whereNull('deleted_at')->first();
    $response->assertSessionHas('success', "Transfer slip {$transferSlip->ts_number} has been updated successfully.");

    expect($transferSlip)->not->toBeNull()
        ->and((string) $transferSlip->ts_date)->toStartWith($newDate)
        ->and((int) $transferSlip->for_production)->toBe(1)
        ->and($transferSlip->remarks)->toBe('Updated TS');

    $activeItems = DB::table('transfer_slip_items')
        ->where('transfer_slip_id', $transferSlipId)
        ->whereNull('deleted_at')
        ->orderBy('store_withdrawal_item_id')
        ->get();

    expect($activeItems)->toHaveCount(2)
        ->and((float) $activeItems->firstWhere('store_withdrawal_item_id', $swsItemA)->quantity)->toBe(6.0)
        ->and((float) $activeItems->firstWhere('store_withdrawal_item_id', $swsItemB)->quantity)->toBe(2.0);

    expect(netQtyOut1($transferSlipId))->toBe(8.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(94.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemB->id)->value('balance'))->toBe(98.0)
        ->and((float) Item::query()->where('id', $this->itemA->id)->value('stock_on_hand'))->toBe(94.0)
        ->and((float) Item::query()->where('id', $this->itemB->id)->value('stock_on_hand'))->toBe(98.0);
});

it('rejects quantity that exceeds remaining after excluding the current transfer slip', function () {
    [$storeWithdrawalId, $swsItemA, $swsItemB, $now] = createNormalSwsWithTwoItems($this, 10, 10);
    $swsNumber = DB::table('store_withdrawals')->where('id', $storeWithdrawalId)->value('sws_number');

    $firstTsId = createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 4,
        ],
    ], $now->toDateString());

    createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 4,
        ],
    ], $now->toDateString());

    $balanceBefore = (float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance');

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->put(route('transfer-slips.update', $firstTsId), [
            '_edit_transfer_slip_id' => $firstTsId,
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => $swsNumber,
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $swsItemA,
                    'item_id' => $this->itemA->id,
                    'quantity' => 7,
                ],
                [
                    'store_withdrawal_item_id' => $swsItemB,
                    'item_id' => $this->itemB->id,
                    'quantity' => 0,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasErrors('items');

    expect(netQtyOut1($firstTsId))->toBe(4.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe($balanceBefore);
});

it('removes an item and adds another from the same sws while adjusting stock', function () {
    [$storeWithdrawalId, $swsItemA, $swsItemB, $now] = createNormalSwsWithTwoItems($this);
    $swsNumber = DB::table('store_withdrawals')->where('id', $storeWithdrawalId)->value('sws_number');

    $transferSlipId = createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 5,
        ],
    ], $now->toDateString());

    expect((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(95.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemB->id)->value('balance'))->toBe(100.0);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->put(route('transfer-slips.update', $transferSlipId), [
            '_edit_transfer_slip_id' => $transferSlipId,
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => $swsNumber,
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $swsItemA,
                    'item_id' => $this->itemA->id,
                    'quantity' => 0,
                ],
                [
                    'store_withdrawal_item_id' => $swsItemB,
                    'item_id' => $this->itemB->id,
                    'quantity' => 6,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    $activeItems = DB::table('transfer_slip_items')
        ->where('transfer_slip_id', $transferSlipId)
        ->whereNull('deleted_at')
        ->get();

    expect($activeItems)->toHaveCount(1)
        ->and((int) $activeItems->first()->store_withdrawal_item_id)->toBe($swsItemB)
        ->and((float) $activeItems->first()->quantity)->toBe(6.0);

    expect(netQtyOut1($transferSlipId))->toBe(6.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(100.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemB->id)->value('balance'))->toBe(94.0);
});

it('forbids update without update-transfer permission', function () {
    [$storeWithdrawalId, $swsItemA, , $now] = createNormalSwsWithTwoItems($this);
    $swsNumber = DB::table('store_withdrawals')->where('id', $storeWithdrawalId)->value('sws_number');

    $transferSlipId = createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 2,
        ],
    ], $now->toDateString());

    $viewer = User::query()->create([
        'name' => 'TS View Only',
        'username' => 'ts-view-only',
        'email' => 'ts-view-only@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $viewRole = Role::findOrCreate('ts-view-only-role');
    $viewRole->syncPermissions(['view-transfer']);
    $viewer->assignRole($viewRole);

    $response = $this->actingAs($viewer)
        ->put(route('transfer-slips.update', $transferSlipId), [
            '_edit_transfer_slip_id' => $transferSlipId,
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => $swsNumber,
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $swsItemA,
                    'item_id' => $this->itemA->id,
                    'quantity' => 3,
                ],
            ],
        ]);

    $response->assertForbidden();
    expect(netQtyOut1($transferSlipId))->toBe(2.0);
});

it('allows confirmatory transfer slip edit when stock becomes more negative', function () {
    $now = now();

    StockInventory::query()->where('item_id', $this->itemA->id)->update([
        'balance' => 0,
        'start_balance' => 0,
    ]);
    Item::query()->where('id', $this->itemA->id)->update(['stock_on_hand' => 0]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-CONF-EDIT-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'confirmatory',
        'info' => 'Confirmatory edit SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemA = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $this->itemA->id,
        'product_code' => $this->itemA->code,
        'quantity' => 10,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $transferSlipId = createTransferSlip($this, $storeWithdrawalId, [
        [
            'store_withdrawal_item_id' => $swsItemA,
            'item_id' => $this->itemA->id,
            'quantity' => 3,
        ],
    ], $now->toDateString());

    expect((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(-3.0);

    $response = $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->put(route('transfer-slips.update', $transferSlipId), [
            '_edit_transfer_slip_id' => $transferSlipId,
            'ts_date' => $now->toDateString(),
            'for_production' => '0',
            'sws_number' => 'SWS-CONF-EDIT-001',
            'store_withdrawal_id' => $storeWithdrawalId,
            'items' => [
                [
                    'store_withdrawal_item_id' => $swsItemA,
                    'item_id' => $this->itemA->id,
                    'quantity' => 7,
                ],
            ],
        ]);

    $response->assertRedirect(route('transfer-slips.index'));
    $response->assertSessionHasNoErrors();

    expect(netQtyOut1($transferSlipId))->toBe(7.0)
        ->and((float) StockInventory::query()->where('item_id', $this->itemA->id)->value('balance'))->toBe(-7.0);
});
