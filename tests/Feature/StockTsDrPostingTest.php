<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockBalance;
use App\Models\StockInventory;
use App\Models\UnitOfMeasure;
use App\Services\StockService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-STOCK',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-STOCK',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Stock Posting Item',
        'code' => 'STOCK-POST-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Spare Parts',
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

    $this->stockService = app(StockService::class);
});

it('posts transfer slip issues to qty_out1 and reduces inventory', function () {
    $this->stockService->applyTransferSlipIssue(
        transferSlipId: 501,
        movementDate: now()->toDateString(),
        lines: [[
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'quantity' => 15,
            'reference_line_id' => 9001,
        ]],
    );

    $inventory = StockInventory::query()->where('item_id', $this->item->id)->first();
    expect((float) $inventory->balance)->toBe(85.0);
    expect((int) $this->item->fresh()->stock_on_hand)->toBe(85);

    $balance = StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', 501)
        ->where('reference_line_id', 9001)
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->qty_out1)->toBe(15.0)
        ->and((float) $balance->qty_out3)->toBe(0.0)
        ->and((float) $balance->end)->toBe(85.0);
});

it('posts delivery issues to qty_out3 and reduces inventory', function () {
    $this->stockService->applyDeliveryIssue(
        deliveryId: 601,
        movementDate: now()->toDateString(),
        lines: [[
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'quantity' => 12,
            'reference_line_id' => 9002,
        ]],
    );

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(88.0);

    $balance = StockBalance::query()
        ->where('reference_type', StockService::REF_DELIVERY)
        ->where('reference_id', 601)
        ->where('reference_line_id', 9002)
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->qty_out3)->toBe(12.0)
        ->and((float) $balance->qty_out1)->toBe(0.0);
});

it('reverses transfer slip issues back into inventory', function () {
    $lines = [[
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 20,
        'reference_line_id' => 9003,
    ]];

    $this->stockService->applyTransferSlipIssue(701, now()->toDateString(), $lines);
    $this->stockService->reverseTransferSlipIssue(701, now()->toDateString(), $lines);

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);
    expect((float) StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', 701)
        ->sum('qty_out1'))->toBe(0.0);
});

it('rejects transfer slip issue when stock is insufficient', function () {
    $this->stockService->applyTransferSlipIssue(
        transferSlipId: 801,
        movementDate: now()->toDateString(),
        lines: [[
            'item_id' => $this->item->id,
            'product_code' => $this->item->code,
            'quantity' => 150,
            'reference_line_id' => 9004,
        ]],
    );
})->throws(ValidationException::class);

it('skips duplicate transfer slip posts for the same reference line', function () {
    $lines = [[
        'item_id' => $this->item->id,
        'product_code' => $this->item->code,
        'quantity' => 10,
        'reference_line_id' => 9005,
    ]];

    $this->stockService->applyTransferSlipIssue(901, now()->toDateString(), $lines);
    $this->stockService->applyTransferSlipIssue(901, now()->toDateString(), $lines);

    expect(StockBalance::query()
        ->where('reference_type', StockService::REF_TRANSFER_SLIP)
        ->where('reference_id', 901)
        ->where('reference_line_id', 9005)
        ->count())->toBe(1);
    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(90.0);
});
