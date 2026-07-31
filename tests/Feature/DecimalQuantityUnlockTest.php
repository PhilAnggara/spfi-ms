<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ReceivingReportItem;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\TransferSlipItem;
use App\Models\UnitOfMeasure;
use App\Services\StockService;
use App\Support\PdfFormatters;

it('casts stock and rr quantity fields to five decimal places', function () {
    expect((new StockBalance)->getCasts())
        ->toMatchArray([
            'begin' => 'decimal:5',
            'qty_in1' => 'decimal:5',
            'qty_out1' => 'decimal:5',
            'end' => 'decimal:5',
            'acc_qty_in1' => 'decimal:5',
            'acc_qty_total' => 'decimal:5',
            'acc_average_price_in1' => 'decimal:2',
            'acc_average_price_total' => 'decimal:2',
        ]);

    expect((new StockInventory)->getCasts())
        ->toMatchArray([
            'balance' => 'decimal:5',
            'start_balance' => 'decimal:5',
            'average_price' => 'decimal:2',
        ]);

    expect((new ReceivingReportItem)->getCasts())
        ->toMatchArray([
            'qty_good' => 'decimal:5',
            'qty_bad' => 'decimal:5',
        ]);

    expect((new TransferSlipItem)->getCasts())
        ->toMatchArray([
            'quantity' => 'decimal:5',
        ]);

    expect((new Item)->getCasts())
        ->toHaveKey('stock_on_hand', 'decimal:5');
});

it('posts fractional stock movements at five decimal precision', function () {
    $unit = UnitOfMeasure::query()->create([
        'name' => 'Kilogram',
        'code' => 'KG-DEC',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Raw Materials',
        'code' => 'RAW-DEC',
    ]);

    $item = Item::query()->create([
        'name' => 'Decimal Stock Item',
        'code' => 'DEC-STOCK-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $item->id,
        'product_code' => $item->code,
        'wh_code' => 'MAIN',
        'balance' => 10,
        'start_balance' => 10,
        'average_price' => 12.5,
        'is_active' => true,
        'is_delete' => false,
    ]);

    app(StockService::class)->applyDeliveryIssue(
        deliveryId: 7001,
        movementDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'product_code' => $item->code,
            'quantity' => 0.00001,
            'reference_line_id' => 8001,
        ]],
    );

    $inventory = StockInventory::query()->where('item_id', $item->id)->first();
    expect((float) $inventory->balance)->toBe(9.99999);
    expect((float) $item->fresh()->stock_on_hand)->toBe(9.99999);

    $balance = StockBalance::query()
        ->where('reference_type', StockService::REF_DELIVERY)
        ->where('reference_id', 7001)
        ->where('reference_line_id', 8001)
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->qty_out3)->toBe(0.00001)
        ->and((float) $balance->end)->toBe(9.99999)
        ->and(PdfFormatters::qty($balance->qty_out3))->toBe('0,00001');
});

it('syncs fractional inventory balance to items stock_on_hand', function () {
    $unit = UnitOfMeasure::query()->create([
        'name' => 'Kilogram',
        'code' => 'KG-SOH',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Raw Materials',
        'code' => 'RAW-SOH',
    ]);

    $item = Item::query()->create([
        'name' => 'Fractional SOH Item',
        'code' => 'SOH-FRAC-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 25,
        'is_active' => true,
    ]);

    StockInventory::query()->create([
        'item_id' => $item->id,
        'product_code' => $item->code,
        'wh_code' => 'MAIN',
        'balance' => 25.2,
        'start_balance' => 25.2,
        'average_price' => 10,
        'is_active' => true,
        'is_delete' => false,
    ]);

    app(StockService::class)->applyTransferSlipIssue(
        transferSlipId: 7101,
        movementDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'product_code' => $item->code,
            'quantity' => 0.1,
            'reference_line_id' => 8101,
        ]],
    );

    expect((float) StockInventory::query()->where('item_id', $item->id)->value('balance'))->toBe(25.1);
    expect((float) $item->fresh()->stock_on_hand)->toBe(25.1);
});
