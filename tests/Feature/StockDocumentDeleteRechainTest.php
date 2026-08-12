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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Rechain',
        'code' => '7042-RCH',
        'alias' => 'IM-RCH',
    ]);

    $this->user = User::query()->create([
        'name' => 'Rechain User',
        'username' => 'rechain-user',
        'email' => 'rechain-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $this->supplier = Supplier::query()->create([
        'name' => 'Rechain Supplier',
        'code' => 'SUP-RCH',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-RCH',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-RCH',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Rechain Item',
        'code' => '93521-RCH',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
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

it('deletes a prior-month RR and rechains later ledger so IM report ending matches on-hand', function () {
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RCH-RR-001',
        'subtotal' => 800,
        'total' => 800,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 80,
        'unit_price' => 10,
        'line_subtotal' => 800,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 800,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-RCH-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-07-15',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'qty_good' => 80,
        'qty_bad' => 0,
    ]);

    app(StockService::class)->applyReceivingReportAdjustment(
        receivingReport: $receivingReport,
        currentLines: [[
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'qty_good' => 80,
            'unit_price' => 10,
            'wh_code' => 'MAIN',
        ]],
        previousLines: [],
        userId: $this->user->id,
    );

    StockBalance::query()->create([
        'date' => '2026-08-10',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 80,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 80,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 80,
        'acc_average_price_total' => 10,
        'reference_type' => 'seed',
        'reference_id' => 1,
        'reference_line_id' => 1,
    ]);

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(80.0);

    $this->actingAs($this->user)
        ->delete(route('receiving-reports.destroy', $receivingReport))
        ->assertRedirect();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(0.0);

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $receivingReport->id)
        ->count())->toBe(0);

    $augustRow = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->whereDate('date', '2026-08-10')
        ->first();

    expect($augustRow)->not->toBeNull()
        ->and((float) $augustRow->begin)->toBe(0.0)
        ->and((float) $augustRow->end)->toBe(0.0);

    $response = $this->actingAs($this->user)->post(route('im.reports.stock-inventory'), [
        'as_of' => '2026-08-31',
        'category' => 'SPARE PARTS',
        'format' => 'excel',
    ]);

    $response->assertSuccessful();

    $sheet = loadStockInventorySheetForRechain($response);
    $endingByCode = [];
    for ($row = 8; $row <= 40; $row++) {
        $code = trim((string) $sheet->getCell('B'.$row)->getValue());
        if ($code === '') {
            break;
        }
        $endingByCode[$code] = (float) $sheet->getCell('I'.$row)->getValue();
    }

    expect($endingByCode[$this->item->code] ?? 0.0)->toBe(0.0);
});

it('deletes a TS and rechains later ledger ends', function () {
    StockInventory::query()->where('item_id', $this->item->id)->update(['balance' => 100]);
    Item::query()->whereKey($this->item->id)->update(['stock_on_hand' => 100]);

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

    $now = now();
    $swsId = (int) DB::table('store_withdrawals')->insertGetId([
        'sws_number' => 'SWS-RCH-TS-001',
        'sws_date' => '2026-08-01',
        'department_id' => $this->department->id,
        'department_code' => $this->department->code,
        'type' => 'regular',
        'info' => 'Rechain TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $swsItemId = (int) DB::table('store_withdrawal_items')->insertGetId([
        'store_withdrawal_id' => $swsId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 30,
        'uom' => 'PCS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tsId = (int) DB::table('transfer_slips')->insertGetId([
        'ts_number' => 'TS-RCH-001',
        'ts_date' => '2026-08-05',
        'store_withdrawal_id' => $swsId,
        'remarks' => 'Rechain TS',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $tsiId = (int) DB::table('transfer_slip_items')->insertGetId([
        'transfer_slip_id' => $tsId,
        'store_withdrawal_item_id' => $swsItemId,
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 30,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    app(StockService::class)->applyTransferSlipIssue(
        transferSlipId: $tsId,
        movementDate: '2026-08-05',
        lines: [[
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'quantity' => 30,
            'reference_line_id' => $tsiId,
            'wh_code' => 'MAIN',
        ]],
        userId: $this->user->id,
    );

    StockBalance::query()->create([
        'date' => '2026-08-20',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 70,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 70,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 70,
        'acc_average_price_total' => 10,
        'reference_type' => 'seed',
        'reference_id' => 2,
        'reference_line_id' => 2,
    ]);

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(70.0);

    $this->actingAs($this->user)
        ->delete(route('transfer-slips.destroy', $tsId))
        ->assertRedirect();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);
    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', $tsId)
        ->count())->toBe(0);

    $later = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->whereDate('date', '2026-08-20')
        ->first();

    expect($later)->not->toBeNull()
        ->and((float) $later->begin)->toBe(100.0)
        ->and((float) $later->end)->toBe(100.0);
});

it('rechains an item via artisan without changing on-hand', function () {
    StockBalance::query()->create([
        'date' => '2026-07-15',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 0,
        'qty_in1' => 80,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 80,
        'acc_qty_in1' => 80,
        'acc_average_price_in1' => 10,
        'acc_qty_total' => 80,
        'acc_average_price_total' => 10,
        'reference_type' => StockService::REF_RECEIVING_REPORT,
        'reference_id' => 99,
        'reference_line_id' => 1,
    ]);

    StockBalance::query()->create([
        'date' => '2026-07-15',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 80,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 80,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 0,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 0,
        'acc_average_price_total' => 0,
        'reference_type' => StockService::REF_RECEIVING_REPORT,
        'reference_id' => 99,
        'reference_line_id' => 1,
    ]);

    StockBalance::query()->create([
        'date' => '2026-08-10',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 80,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => 80,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => 80,
        'acc_average_price_total' => 0,
        'reference_type' => 'seed',
        'reference_id' => 3,
        'reference_line_id' => 3,
    ]);

    StockInventory::query()->where('item_id', $this->item->id)->update(['balance' => 0]);

    $exit = Artisan::call('stock:rechain', [
        'item_code' => $this->item->code,
        '--from' => '2026-07-15',
        '--wh' => 'MAIN',
    ]);

    expect($exit)->toBe(0);
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(0.0);

    $august = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->whereDate('date', '2026-08-10')
        ->first();

    expect((float) $august->begin)->toBe(0.0)
        ->and((float) $august->end)->toBe(0.0);
});

function loadStockInventorySheetForRechain(TestResponse $response): Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'im-rechain-xlsx');
    file_put_contents($tmp, $response->streamedContent());

    try {
        return IOFactory::load($tmp)->getActiveSheet();
    } finally {
        @unlink($tmp);
    }
}
