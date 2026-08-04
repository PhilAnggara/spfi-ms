<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->travelTo(now()->startOfMonth()->addDays(10));

    $department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-BF',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-BF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Backfill Item',
        'code' => 'BF-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 200,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => 200,
        'start_balance' => 200,
        'average_price' => 5,
        'is_active' => true,
        'is_delete' => false,
    ]);

    $user = User::query()->create([
        'name' => 'Backfill User',
        'username' => 'backfill-user',
        'email' => 'backfill-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Backfill Supplier',
        'code' => 'SUP-BF-001',
        'created_by' => $user->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-BF-001',
    ]);

    $this->poItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 30,
        'unit_price' => 5,
        'line_subtotal' => 150,
        'total' => 150,
    ]);

    $this->rr = ReceivingReport::query()->create([
        'rr_number' => 'RR-BF-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->rr->id,
        'purchase_order_item_id' => $this->poItem->id,
        'qty_good' => 30,
        'qty_bad' => 0,
    ]);

    $now = now();

    $swsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-BF-001',
        'sws_date' => now()->toDateString(),
        'department_id' => $department->id,
        'department_code' => $department->code,
        'type' => 'regular',
        'info' => 'Backfill SWS',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $swsId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 10,
        'uom' => 'PCS',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->tsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-BF-001',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $swsId,
        'for_production' => false,
        'remarks' => null,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->tsItemId = (int) DB::table('transfer_slip_items')->insertGetId([
        'transfer_slip_id' => $this->tsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 10,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->drId = (int) DB::table('deliveries')->insertGetId([
        'dr_number' => 'DR-BF-001',
        'dr_date' => now()->toDateString(),
        'from_name' => 'IM',
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->drItemId = (int) DB::table('delivery_items')->insertGetId([
        'delivery_id' => $this->drId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'uom' => 'PCS',
        'quantity' => 5,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $priorTsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-BF-OLD',
        'ts_date' => now()->subMonth()->toDateString(),
        'store_withdrawal_id' => $swsId,
        'for_production' => false,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $priorTsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 99,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('dry-run lists missing current-month docs without writing balances', function () {
    $this->artisan('stock:backfill-current-month', ['--dry-run' => true])
        ->assertSuccessful();

    expect(StockBalance::query()->count())->toBe(0);
});

it('backfills missing current-month rr ts and dr into the ledger', function () {
    $this->artisan('stock:backfill-current-month', ['--force' => true])
        ->assertSuccessful();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $this->rr->id)
        ->where('reference_line_id', $this->poItem->id)
        ->exists())->toBeTrue();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $this->tsId)
        ->where('reference_line_id', $this->tsItemId)
        ->exists())->toBeTrue();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_DELIVERY)
        ->where('reference_id', $this->drId)
        ->where('reference_line_id', $this->drItemId)
        ->exists())->toBeTrue();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('qty_out1', 99)
        ->exists())->toBeFalse();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);
});

it('skips already posted references on a second backfill run', function () {
    $this->artisan('stock:backfill-current-month', ['--force' => true])->assertSuccessful();
    $countAfterFirst = StockBalance::query()->count();

    $this->artisan('stock:backfill-current-month', ['--force' => true])->assertSuccessful();

    expect(StockBalance::query()->count())->toBe($countAfterFirst);
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);
});

it('limits the window with --from and --to', function () {
    $this->artisan('stock:backfill-current-month', [
        '--from' => now()->addDay()->toDateString(),
        '--to' => now()->addDays(2)->toDateString(),
        '--force' => true,
    ])->assertSuccessful();

    expect(StockBalance::query()->count())->toBe(0);
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(200.0);

    $this->artisan('stock:backfill-current-month', [
        '--from' => now()->startOfMonth()->toDateString(),
        '--to' => now()->toDateString(),
        '--force' => true,
    ])->assertSuccessful();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);
    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('qty_out1', 99)
        ->exists())->toBeFalse();
});

it('rebuild purges then re-posts without stacking duplicate ledger rows', function () {
    $this->artisan('stock:backfill-current-month', ['--force' => true])->assertSuccessful();
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);

    $this->artisan('stock:backfill-current-month', [
        '--from' => now()->startOfMonth()->toDateString(),
        '--to' => now()->toDateString(),
        '--rebuild' => true,
        '--force' => true,
    ])->assertSuccessful();

    $this->artisan('stock:backfill-current-month', [
        '--from' => now()->startOfMonth()->toDateString(),
        '--to' => now()->toDateString(),
        '--rebuild' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $this->rr->id)
        ->count())->toBe(1);

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $this->tsId)
        ->count())->toBe(1);

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_DELIVERY)
        ->where('reference_id', $this->drId)
        ->count())->toBe(1);
});

it('rebuild dry-run does not write ledger rows', function () {
    $this->artisan('stock:backfill-current-month', [
        '--from' => now()->startOfMonth()->toDateString(),
        '--rebuild' => true,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(StockBalance::query()->count())->toBe(0);
});

it('skips reconcile-alias transfer slips when posting stock', function () {
    $now = now();

    $aliasTsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => '011999',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => DB::table('transfer_slips')->where('id', $this->tsId)->value('store_withdrawal_id'),
        'for_production' => false,
        'remarks' => null,
        'created_by' => User::query()->value('id'),
        'meta' => json_encode([
            'legacy_ts_code' => '047999',
            'aliased_from' => '047999',
            'reconcile_import' => true,
        ]),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $aliasLineId = (int) DB::table('transfer_slip_items')->insertGetId([
        'transfer_slip_id' => $aliasTsId,
        'store_withdrawal_item_id' => DB::table('transfer_slip_items')->where('id', $this->tsItemId)->value('store_withdrawal_item_id'),
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 10,
        'created_by' => User::query()->value('id'),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if (Schema::hasTable('reconciliation_number_maps')) {
        DB::table('reconciliation_number_maps')->insert([
            'document_type' => 'ts',
            'ims_number' => '047999',
            'spfi_number' => '011999',
            'existing_spfi_number' => 'TS-BF-001',
            'resolution' => 'import_as_alias',
            'ims_fingerprint' => 'a',
            'spfi_fingerprint' => 'b',
            'meta' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $this->artisan('stock:backfill-current-month', ['--force' => true])->assertSuccessful();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $aliasTsId)
        ->exists())->toBeFalse();

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $this->tsId)
        ->where('reference_line_id', $this->tsItemId)
        ->exists())->toBeTrue();

    // Only one TS issue of 10, not doubled by alias.
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(215.0);
    expect($aliasLineId)->toBeGreaterThan(0);
});
