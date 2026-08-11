<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\OpeningBalanceCorrection;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042-OBC',
        'alias' => 'IM-OBC',
    ]);

    $this->user = User::query()->create([
        'name' => 'OBC User',
        'username' => 'obc-user',
        'email' => 'obc-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-OBC',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-OBC',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Opening Correction Item',
        'code' => 'OBC-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 90,
        'is_active' => true,
    ]);

    $this->otherItem = Item::query()->create([
        'name' => 'Untouched Item',
        'code' => 'OBC-ITEM-002',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 50,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 90,
        'start_balance' => 100,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->otherItem->id,
        'product_code' => $this->otherItem->code,
        'wh_code' => 'MAIN',
        'balance' => 50,
        'start_balance' => 50,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);

    // Wrong beginning chain for August: begin 100, RR +20, TS -30 → end 90
    StockBalance::query()->create([
        'date' => '2026-07-31',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 100,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 100,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 100,
        'acc_average_price_total' => 10,
        'reference_type' => 'seed',
        'reference_id' => 1,
        'reference_line_id' => 1,
    ]);

    StockBalance::query()->create([
        'date' => '2026-08-05',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 100,
        'qty_in1' => 20,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 120,
        'acc_qty_in1' => 20,
        'acc_average_price_in1' => 10,
        'acc_qty_total' => 120,
        'acc_average_price_total' => 10,
        'reference_type' => StockService::REF_RECEIVING_REPORT,
        'reference_id' => 9001,
        'reference_line_id' => 90011,
    ]);

    StockBalance::query()->create([
        'date' => '2026-08-07',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 120,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 30,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 90,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 90,
        'acc_average_price_total' => 10,
        'reference_type' => StockService::REF_TRANSFER_SLIP,
        'reference_id' => 9002,
        'reference_line_id' => 90021,
    ]);
});

it('corrects period beginning and rebuilds later movements when documents exist', function () {
    // Without live RR/TS documents, purge removes old ledger rows and opening is set;
    // final balance becomes the new beginning (no replayable docs).
    $response = $this->actingAs($this->user)->post(route('opening-balance-corrections.store'), [
        'period_month' => '2026-08',
        'reason' => 'Align with Excel ending July',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_beginning' => 150,
                'wh_code' => 'MAIN',
            ],
        ],
    ]);

    $response->assertRedirect();

    $correction = OpeningBalanceCorrection::query()->first();
    expect($correction)->not->toBeNull();
    expect((float) $correction->items()->first()->previous_beginning)->toBe(100.0);
    expect((float) $correction->items()->first()->new_beginning)->toBe(150.0);

    // August ledger rows purged; opening adj dated 2026-07-31
    expect(StockBalance::query()
        ->where('item_id', $this->item->id)
        ->whereDate('date', '>=', '2026-08-01')
        ->count())->toBe(0);

    $opening = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('reference_type', StockService::REF_OPENING_BALANCE_CORRECTION)
        ->first();

    expect($opening)->not->toBeNull()
        ->and($opening->date->toDateString())->toBe('2026-07-31')
        ->and((float) $opening->qty_in2)->toBe(50.0)
        ->and((float) $opening->end)->toBe(150.0);

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(150.0);

    // Other item untouched
    expect((float) StockInventory::query()->where('item_id', $this->otherItem->id)->value('balance'))->toBe(50.0);
});

it('replays transfer slip after opening correction when TS document exists', function () {
    $now = now();

    $tsId = (int) \Illuminate\Support\Facades\DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-OBC-001',
        'ts_date' => '2026-08-07',
        'store_withdrawal_id' => createMinimalSws($this->department->id, $this->user->id),
        'remarks' => 'OBC replay TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tsiId = (int) \Illuminate\Support\Facades\DB::table('transfer_slip_items')->insertGetId([
        'transfer_slip_id' => $tsId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 30,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Replace fake TS ledger with real reference ids matching the document
    StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', 9002)
        ->delete();

    StockBalance::query()->create([
        'date' => '2026-08-07',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 120,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 30,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 90,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 90,
        'acc_average_price_total' => 10,
        'reference_type' => StockService::REF_TRANSFER_SLIP,
        'reference_id' => $tsId,
        'reference_line_id' => $tsiId,
    ]);

    // Remove fake RR ledger so only TS replays (RR has no document)
    StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->delete();

    // Current balance after removing RR ledger effect: was 90 with RR+20 and TS-30 from begin 100.
    // After deleting RR ledger row without undoing inventory, balance is still 90 but implied begin wrong.
    // Reset inventory to match remaining ledger: begin 100 - TS 30 = 70 if RR gone from ledger only...
    // Simpler: set inventory to 70 (100 begin - 30 TS) and keep July 31 end 100 + Aug TS only.
    StockInventory::query()->where('item_id', $this->item->id)->update(['balance' => 70]);
    Item::query()->whereKey($this->item->id)->update(['stock_on_hand' => 70]);

    $this->actingAs($this->user)->post(route('opening-balance-corrections.store'), [
        'period_month' => '2026-08',
        'reason' => 'Rebuild with TS',
        'confirmed' => '1',
        'allow_negative_balance' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_beginning' => 150,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertRedirect();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(120.0);

    $tsLedger = StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $tsId)
        ->where('reference_line_id', $tsiId)
        ->first();

    expect($tsLedger)->not->toBeNull()
        ->and((float) $tsLedger->begin)->toBe(150.0)
        ->and((float) $tsLedger->qty_out1)->toBe(30.0)
        ->and((float) $tsLedger->end)->toBe(120.0);
});

it('previews implied beginning and replay count', function () {
    $response = $this->actingAs($this->user)->postJson(route('opening-balance-corrections.preview'), [
        'period_month' => '2026-08',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_beginning' => 150,
                'wh_code' => 'MAIN',
            ],
        ],
    ]);

    $response->assertSuccessful();
    $preview = $response->json('previews.0');

    expect((float) $preview['previous_beginning'])->toBe(100.0)
        ->and((float) $preview['new_beginning'])->toBe(150.0)
        ->and((float) $preview['delta_qty'])->toBe(50.0);
});

it('searches opening-balance items by name', function () {
    $response = $this->actingAs($this->user)->getJson(route('opening-balance-corrections.items.search', [
        'q' => 'Opening Correction',
    ]));

    $response->assertSuccessful();
    expect(collect($response->json('items'))->pluck('code'))->toContain('OBC-ITEM-001');
});

function createMinimalSws(int $departmentId, int $userId): int
{
    $now = now();

    return (int) \Illuminate\Support\Facades\DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-OBC-'.uniqid(),
        'sws_date' => '2026-08-01',
        'department_id' => $departmentId,
        'department_code' => '7042',
        'type' => 'regular',
        'info' => 'OBC test SWS',
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}
