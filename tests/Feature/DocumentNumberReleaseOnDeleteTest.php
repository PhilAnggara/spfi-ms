<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\DocumentNumberService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7100',
        'alias' => 'IM',
    ]);

    $this->user = User::query()->create([
        'name' => 'Number Release User',
        'username' => 'number-release-user',
        'email' => 'number-release-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('administrator');

    $this->supplier = Supplier::query()->create([
        'name' => 'Release Number Supplier',
        'code' => 'SUP-REL-NUM',
        'created_by' => $this->user->id,
    ]);
});

it('releases the rr number on delete so it can be reused', function () {
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-REL-RR-001',
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-REUSE-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->from(route('receiving-reports.index'))
        ->delete(route('receiving-reports.destroy', $receivingReport))
        ->assertRedirect(route('receiving-reports.index'))
        ->assertSessionHas('success');

    $deleted = ReceivingReport::withTrashed()->find($receivingReport->id);

    expect($deleted)->not->toBeNull()
        ->and($deleted->trashed())->toBeTrue()
        ->and($deleted->rr_number)->toBe('DELETED-'.$receivingReport->id);

    app(DocumentNumberService::class)->assertUnique('RR', 'RR-REUSE-001');

    $reused = ReceivingReport::query()->create([
        'rr_number' => 'RR-REUSE-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    expect($reused->fresh()->rr_number)->toBe('RR-REUSE-001');
});

it('releases the ts number on delete so it can be reused', function () {
    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);
    $category = ItemCategory::query()->create([
        'name' => 'Consumables',
        'code' => 'CNS',
    ]);
    $item = Item::query()->create([
        'name' => 'TS Release Item',
        'code' => 'TS-REL-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 20,
        'is_active' => true,
    ]);

    $now = now();

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-REL-TS-001',
        'sws_date' => $now->toDateString(),
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Release test SWS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $storeWithdrawalItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $storeWithdrawalId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 5,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-REUSE-001',
        'ts_date' => $now->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'remarks' => 'Release test TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItemId,
        'item_id' => $item->id,
        'product_code' => $item->code,
        'quantity' => 2,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user)
        ->from(route('transfer-slips.index'))
        ->delete(route('transfer-slips.destroy', $transferSlipId))
        ->assertRedirect(route('transfer-slips.index'))
        ->assertSessionHas('success');

    $deleted = DB::table('transfer_slips')->where('id', $transferSlipId)->first();

    expect($deleted)->not->toBeNull()
        ->and($deleted->deleted_at)->not->toBeNull()
        ->and($deleted->ts_number)->toBe('DELETED-'.$transferSlipId);

    app(DocumentNumberService::class)->assertUnique('TS', 'TS-REUSE-001');

    $reusedId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-REUSE-001',
        'ts_date' => $now->toDateString(),
        'store_withdrawal_id' => $storeWithdrawalId,
        'for_production' => false,
        'remarks' => 'Reused TS number',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(DB::table('transfer_slips')->where('id', $reusedId)->value('ts_number'))->toBe('TS-REUSE-001');
});

it('releases the dr number on delete so it can be reused', function () {
    $now = now();

    $deliveryId = (int) DB::table('deliveries')->insertGetId([
        'dr_number' => 'DR-REUSE-001',
        'dr_date' => $now->toDateString(),
        'from_name' => 'IM - PT. SPFI',
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($this->user)
        ->from(route('deliveries.index'))
        ->delete(route('deliveries.destroy', $deliveryId))
        ->assertRedirect(route('deliveries.index'))
        ->assertSessionHas('success');

    $deleted = DB::table('deliveries')->where('id', $deliveryId)->first();

    expect($deleted)->not->toBeNull()
        ->and($deleted->deleted_at)->not->toBeNull()
        ->and($deleted->dr_number)->toBe('DELETED-'.$deliveryId);

    app(DocumentNumberService::class)->assertUnique('DR', 'DR-REUSE-001');

    $reusedId = (int) DB::table('deliveries')->insertGetId([
        'dr_number' => 'DR-REUSE-001',
        'dr_date' => $now->toDateString(),
        'from_name' => 'IM - PT. SPFI',
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect(DB::table('deliveries')->where('id', $reusedId)->value('dr_number'))->toBe('DR-REUSE-001');
});
