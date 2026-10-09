<?php

use App\Models\AccountingInventoryMonthly;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\NonFgCountTag;
use App\Models\UnitOfMeasure;
use App\Services\Accounting\AccountingInventoryReportService;

beforeEach(function () {
    $this->category = ItemCategory::query()->create([
        'name' => 'PARTS',
        'code' => 'PARTS-RPT',
    ]);

    $this->unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-RPT',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Local Count Tag Report Item',
        'code' => 'LCTREP01',
        'unit_of_measure_id' => $this->unit->id,
        'category_id' => $this->category->id,
        'type' => 'Spare Part',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    config(['accounting_inventory.count_tag.source' => 'local']);
});

it('reads percount qty from local non_fg_count_tags when source is local', function () {
    $nextMonthStart = now()->copy()->addMonthNoOverflow()->startOfMonth();

    AccountingInventoryMonthly::query()->create([
        'item_code' => $this->item->code,
        'doc_code' => 'RR',
        'doc_no' => 'RR-LOCAL-CT-001',
        'qty' => 4,
        'u_cost' => 10,
        'begining' => 0,
        'ending' => 4,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    NonFgCountTag::query()->create([
        'legacy_id' => 90001,
        'count_tag_number' => 'CT-90001',
        'count_tag_date' => $nextMonthStart->toDateString(),
        'item_id' => $this->item->id,
        'item_code' => $this->item->code,
        'item_category_id' => $this->category->id,
        'qty' => 7,
        'tran_date' => $nextMonthStart->toDateString(),
        'created_by_name' => 'TEST',
    ]);

    $row = app(AccountingInventoryReportService::class)->restatementRows(
        now()->format('Y-m'),
        $this->category->id,
    )->firstWhere('item_code', $this->item->code);

    expect($row)->not->toBeNull();
    expect((float) $row['percount_qty'])->toBe(7.0);
    expect((float) $row['variance_qty'])->toBe(3.0);
});

it('falls back to report month-end local count tag when next month has no tag', function () {
    AccountingInventoryMonthly::query()->create([
        'item_code' => $this->item->code,
        'doc_code' => 'RR',
        'doc_no' => 'RR-LOCAL-CT-002',
        'qty' => 2,
        'u_cost' => 5,
        'begining' => 0,
        'ending' => 2,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'category' => $this->category->name,
        'category_id' => $this->category->id,
        'item_id' => $this->item->id,
    ]);

    NonFgCountTag::query()->create([
        'legacy_id' => 90002,
        'item_id' => $this->item->id,
        'item_code' => $this->item->code,
        'item_category_id' => $this->category->id,
        'qty' => 9,
        'tran_date' => now()->endOfMonth()->toDateString(),
        'created_by_name' => 'TEST',
    ]);

    $row = app(AccountingInventoryReportService::class)->restatementRows(
        now()->format('Y-m'),
        $this->category->id,
    )->firstWhere('item_code', $this->item->code);

    expect($row)->not->toBeNull();
    expect((float) $row['percount_qty'])->toBe(9.0);
});
