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

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Backdate Rechain',
        'code' => '7042-BDR',
        'alias' => 'IM-BDR',
    ]);

    $this->user = User::query()->create([
        'name' => 'Backdate Rechain User',
        'username' => 'backdate-rechain-user',
        'email' => 'backdate-rechain@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $this->supplier = Supplier::query()->create([
        'name' => 'Backdate Supplier',
        'code' => 'SUP-BDR',
        'created_by' => $this->user->id,
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-BDR',
    ]);

    $this->category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-BDR',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Backdate Rechain Item',
        'code' => '20001-BDR',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Parts',
        'stock_on_hand' => -3500,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'balance' => -3500,
        'start_balance' => 0,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);
});

it('rechains ledger after a backdated RR so IM report ending matches on-hand', function () {
    StockBalance::query()->create([
        'date' => '2026-08-01',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => 0,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 3500,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => -3500,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => -3500,
        'acc_average_price_total' => 10,
        'reference_type' => 'seed',
        'reference_id' => 1,
        'reference_line_id' => 1,
    ]);

    StockBalance::query()->create([
        'date' => '2026-08-10',
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'wh_code' => 'MAIN',
        'begin' => -3500,
        'qty_in1' => 0,
        'qty_in2' => 0,
        'qty_in3' => 0,
        'qty_out1' => 0,
        'qty_out2' => 0,
        'qty_out3' => 0,
        'end' => -3500,
        'acc_qty_in1' => 0,
        'acc_average_price_in1' => 0,
        'acc_qty_total' => -3500,
        'acc_average_price_total' => 10,
        'reference_type' => 'seed',
        'reference_id' => 2,
        'reference_line_id' => 2,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-BDR-001',
        'subtotal' => 140000,
        'total' => 140000,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 14000,
        'unit_price' => 10,
        'line_subtotal' => 140000,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 140000,
    ]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-BDR-001',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => '2026-08-05',
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'qty_good' => 14000,
        'qty_bad' => 0,
    ]);

    app(StockService::class)->applyReceivingReportAdjustment(
        receivingReport: $receivingReport,
        currentLines: [[
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'qty_good' => 14000,
            'unit_price' => 10,
            'wh_code' => 'MAIN',
        ]],
        previousLines: [],
        userId: $this->user->id,
    );

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(10500.0);

    $augustRow = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->whereDate('date', '2026-08-10')
        ->first();

    expect($augustRow)->not->toBeNull()
        ->and((float) $augustRow->begin)->toBe(10500.0)
        ->and((float) $augustRow->end)->toBe(10500.0);

    $reportEnding = imReportStockBalanceEnding($this->item->id, '2026-08-31');

    expect($reportEnding)->toBe(10500.0);
});

it('rechains idempotently when posting an RR with no later ledger rows', function () {
    $purchaseOrder = PurchaseOrder::query()->create([
        'supplier_id' => $this->supplier->id,
        'created_by' => $this->user->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-BDR-002',
        'subtotal' => 100,
        'total' => 100,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'item_id' => $this->item->id,
        'quantity' => 10,
        'unit_price' => 10,
        'line_subtotal' => 100,
        'discount_rate' => 0,
        'discount_amount' => 0,
        'ppn_rate' => 0,
        'ppn_amount' => 0,
        'pph_rate' => 0,
        'pph_amount' => 0,
        'total' => 100,
    ]);

    StockInventory::query()->where('item_id', $this->item->id)->update(['balance' => 0]);
    Item::query()->whereKey($this->item->id)->update(['stock_on_hand' => 0]);

    $receivingReport = ReceivingReport::query()->create([
        'rr_number' => 'RR-BDR-002',
        'purchase_order_id' => $purchaseOrder->id,
        'received_date' => now()->toDateString(),
        'created_by' => $this->user->id,
    ]);

    ReceivingReportItem::query()->create([
        'receiving_report_id' => $receivingReport->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'qty_good' => 10,
        'qty_bad' => 0,
    ]);

    app(StockService::class)->applyReceivingReportAdjustment(
        receivingReport: $receivingReport,
        currentLines: [[
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'qty_good' => 10,
            'unit_price' => 10,
            'wh_code' => 'MAIN',
        ]],
        previousLines: [],
        userId: $this->user->id,
    );

    $row = StockBalance::query()
        ->where('item_id', $this->item->id)
        ->where('reference_type', StockService::REF_RECEIVING_REPORT)
        ->where('reference_id', $receivingReport->id)
        ->first();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(10.0)
        ->and($row)->not->toBeNull()
        ->and((float) $row->begin)->toBe(0.0)
        ->and((float) $row->end)->toBe(10.0);
});

function imReportStockBalanceEnding(int $itemId, string $asOf): float
{
    $endColumn = DB::getQueryGrammar()->wrap('sb.end');

    $ending = DB::query()
        ->fromSub(
            DB::table('stock_balances as sb')
                ->where('sb.item_id', $itemId)
                ->whereDate('sb.date', '<=', $asOf)
                ->selectRaw("sb.item_id, sb.wh_code, {$endColumn} as ending_qty, ROW_NUMBER() OVER (PARTITION BY sb.item_id, sb.wh_code ORDER BY sb.date DESC, sb.id DESC) as rn"),
            'ranked'
        )
        ->where('rn', 1)
        ->get()
        ->sum(fn ($row) => (float) $row->ending_qty);

    return (float) $ending;
}
