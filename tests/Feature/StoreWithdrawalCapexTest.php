<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Engineering',
        'code' => '7046',
        'alias' => 'ENG',
    ]);

    $this->user = User::query()->create([
        'name' => 'IM Staff Capex',
        'username' => 'im-staff-capex',
        'email' => 'im-staff-capex@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Machinery',
        'code' => 'MCH',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Capex Machine Part',
        'code' => 'CPX-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Asset',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-CPX-001',
        'name' => 'Capex Supplier',
        'created_by' => $this->user->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '70460000001',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->subDays(10)->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => true,
        'remarks' => 'CAPEX withdraw test PRS',
        'status' => 'PO_CREATED',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 5,
    ]);

    $this->purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-CPX-001',
    ]);

    $this->purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'prs_item_id' => $this->prsItem->id,
        'item_id' => $this->item->id,
        'quantity' => 5,
        'unit_price' => 1000000,
        'total' => 5000000,
    ]);

    $this->receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-CPX-001',
        'purchase_order_id' => $this->purchaseOrder->id,
        'received_date' => now()->subDays(2)->toDateString(),
        'created_by' => $this->user->id,
    ]);

    $this->receivingReportItem = ReceivingReportItem::query()->create([
        'receiving_report_id' => $this->receivingReport->id,
        'purchase_order_item_id' => $this->purchaseOrderItem->id,
        'qty_good' => 5,
        'qty_bad' => 0,
    ]);
});

it('lists only available capex rr lines in catalog endpoint', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('stores-withdrawals.capex-lines', [
            'department_id' => $this->department->id,
        ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.0.receiving_report_item_id', $this->receivingReportItem->id);
    $response->assertJsonPath('data.0.qty_remaining', 5);
});

it('creates capex store withdrawal from rr line', function () {
    $stockBefore = (float) $this->item->fresh()->stock_on_hand;

    $response = $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'CAPEX',
        'info' => 'CAPEX withdraw test',
        'items' => [
            [
                'item_id' => $this->item->id,
                'receiving_report_item_id' => $this->receivingReportItem->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));

    $swsItem = DB::table('store_withdrawal_items')
        ->where('receiving_report_item_id', $this->receivingReportItem->id)
        ->whereNull('deleted_at')
        ->first();

    expect($swsItem)->not->toBeNull();
    expect((float) $swsItem->quantity)->toBe(2.0);
    expect((float) $this->item->fresh()->stock_on_hand)->toBe($stockBefore);

    $catalog = $this->actingAs($this->user)
        ->getJson(route('stores-withdrawals.capex-lines', [
            'department_id' => $this->department->id,
        ]));

    $catalog->assertJsonPath('data.0.qty_remaining', 3);
});

it('rejects capex withdraw quantity above remaining rr balance', function () {
    $response = $this->actingAs($this->user)->from(route('stores-withdrawals.create', ['mode' => 'capex']))
        ->post(route('stores-withdrawals.store'), [
            'department_id' => $this->department->id,
            'sws_date' => now()->toDateString(),
            'type' => 'CAPEX',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'receiving_report_item_id' => $this->receivingReportItem->id,
                    'quantity' => 6,
                ],
            ],
        ]);

    $response->assertRedirect(route('stores-withdrawals.create', ['mode' => 'capex']));
    $response->assertSessionHasErrors('items');
});

it('creates transfer slip from capex store withdrawal', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'CAPEX',
        'items' => [
            [
                'item_id' => $this->item->id,
                'receiving_report_item_id' => $this->receivingReportItem->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $storeWithdrawal = DB::table('store_withdrawals')->where('type', 'capex')->latest('id')->first();
    $storeWithdrawalItem = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawal->id)
        ->first();

    $lookup = $this->actingAs($this->user)
        ->getJson(route('transfer-slips.sws-by-number', [
            'sws_number' => $storeWithdrawal->sws_number,
        ]));

    $lookup->assertSuccessful();
    $lookup->assertJsonPath('store_withdrawal.is_capex', true);
    $lookup->assertJsonPath('items.0.quantity_remaining', 2);

    $response = $this->actingAs($this->user)->post(route('transfer-slips.store'), [
        'ts_date' => now()->toDateString(),
        'for_production' => '0',
        'sws_number' => $storeWithdrawal->sws_number,
        'store_withdrawal_id' => $storeWithdrawal->id,
        'items' => [
            [
                'store_withdrawal_item_id' => $storeWithdrawalItem->id,
                'item_id' => $this->item->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertRedirect(route('transfer-slips.index'));
});

it('locks capex store withdrawal edit after all quantity is transferred', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'CAPEX',
        'items' => [
            [
                'item_id' => $this->item->id,
                'receiving_report_item_id' => $this->receivingReportItem->id,
                'quantity' => 2,
            ],
        ],
    ]);

    $storeWithdrawal = DB::table('store_withdrawals')->where('type', 'capex')->latest('id')->first();
    $storeWithdrawalItem = DB::table('store_withdrawal_items')
        ->where('store_withdrawal_id', $storeWithdrawal->id)
        ->whereNull('deleted_at')
        ->first();

    $transferSlipId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-CPX-LOCK-001',
        'ts_date' => now()->toDateString(),
        'store_withdrawal_id' => $storeWithdrawal->id,
        'for_production' => false,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('transfer_slip_items')->insert([
        'transfer_slip_id' => $transferSlipId,
        'store_withdrawal_item_id' => $storeWithdrawalItem->id,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 2,
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->put(route('stores-withdrawals.update', $storeWithdrawal->id), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'CAPEX',
        'items' => [
            [
                'item_id' => $this->item->id,
                'receiving_report_item_id' => $this->receivingReportItem->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertRedirect(route('stores-withdrawals.index'));
    $response->assertSessionHasErrors('items');
});

it('prints capex store withdrawal slip as pdf', function () {
    $this->actingAs($this->user)->post(route('stores-withdrawals.store'), [
        'department_id' => $this->department->id,
        'sws_date' => now()->toDateString(),
        'type' => 'CAPEX',
        'items' => [
            [
                'item_id' => $this->item->id,
                'receiving_report_item_id' => $this->receivingReportItem->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $storeWithdrawalId = (int) DB::table('store_withdrawals')->where('type', 'capex')->latest('id')->value('id');

    $response = $this->actingAs($this->user)
        ->get(route('stores-withdrawals.print', $storeWithdrawalId));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
