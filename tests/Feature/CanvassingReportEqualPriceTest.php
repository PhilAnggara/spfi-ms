<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsCanvassingItem;
use App\Models\PrsItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->user = User::query()->create([
        'name' => 'Equal Price Canvasser',
        'username' => 'equal-price-canvasser',
        'email' => 'equal-price-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'Office Supplies',
        'code' => 'OFF',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Equal Price Item',
        'code' => 'EQ-PRICE',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $this->supplierA = Supplier::query()->create([
        'code' => 'SUP-EQ-A',
        'name' => 'Equal Supplier A',
        'created_by' => $this->user->id,
    ]);

    $this->supplierB = Supplier::query()->create([
        'code' => 'SUP-EQ-B',
        'name' => 'Equal Supplier B',
        'created_by' => $this->user->id,
    ]);

    $this->prs = Prs::query()->create([
        'prs_number' => '71019990021',
        'user_id' => $this->user->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Equal price canvassing',
        'status' => 'CANVASSING',
    ]);

    $this->prsItem = PrsItem::query()->create([
        'prs_id' => $this->prs->id,
        'item_id' => $this->item->id,
        'quantity' => 2,
        'canvasser_id' => $this->user->id,
        'assigned_canvasser_at' => now(),
    ]);
});

it('renders equal price labels when all supplier quotes match', function () {
    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierA->id,
        'unit_price' => 100,
        'canvased_by' => $this->user->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierB->id,
        'unit_price' => 100,
        'canvased_by' => $this->user->id,
    ]);

    $this->prsItem->load(['prs.department', 'item.unit', 'canvassingItems.supplier']);
    $canvassingItems = $this->prsItem->canvassingItems->sortBy('unit_price')->values();

    $html = view('pdf.partials.canvassing-report-item', [
        'prsItem' => $this->prsItem,
        'canvassingItems' => $canvassingItems,
        'maxUnitPrice' => 100.0,
    ])->render();

    expect($html)
        ->toContain('EQUAL PRICE')
        ->not->toContain('LOWEST PRICE')
        ->not->toContain('(LOWEST)')
        ->not->toContain('(HIGHEST)');
});

it('renders equal lowest labels when only the lowest prices are tied', function () {
    $supplierC = Supplier::query()->create([
        'code' => 'SUP-EQ-C',
        'name' => 'Equal Supplier C',
        'created_by' => $this->user->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierA->id,
        'unit_price' => 80,
        'canvased_by' => $this->user->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierB->id,
        'unit_price' => 80,
        'canvased_by' => $this->user->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $supplierC->id,
        'unit_price' => 120,
        'canvased_by' => $this->user->id,
    ]);

    $this->prsItem->load(['prs.department', 'item.unit', 'canvassingItems.supplier']);
    $canvassingItems = $this->prsItem->canvassingItems->sortBy('unit_price')->values();

    $html = view('pdf.partials.canvassing-report-item', [
        'prsItem' => $this->prsItem,
        'canvassingItems' => $canvassingItems,
        'maxUnitPrice' => 120.0,
    ])->render();

    expect($html)
        ->toContain('EQUAL LOWEST')
        ->toContain('(HIGHEST)')
        ->not->toContain('LOWEST PRICE')
        ->not->toContain('EQUAL PRICE');
});

it('renders a single lowest price label when one quote is uniquely lowest', function () {
    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierA->id,
        'unit_price' => 75,
        'canvased_by' => $this->user->id,
    ]);

    PrsCanvassingItem::query()->create([
        'prs_id' => $this->prs->id,
        'prs_item_id' => $this->prsItem->id,
        'supplier_id' => $this->supplierB->id,
        'unit_price' => 110,
        'canvased_by' => $this->user->id,
    ]);

    $this->prsItem->load(['prs.department', 'item.unit', 'canvassingItems.supplier']);
    $canvassingItems = $this->prsItem->canvassingItems->sortBy('unit_price')->values();

    $html = view('pdf.partials.canvassing-report-item', [
        'prsItem' => $this->prsItem,
        'canvassingItems' => $canvassingItems,
        'maxUnitPrice' => 110.0,
    ])->render();

    expect($html)
        ->toContain('LOWEST PRICE')
        ->toContain('(LOWEST)')
        ->toContain('(HIGHEST)')
        ->not->toContain('EQUAL PRICE')
        ->not->toContain('EQUAL LOWEST');
});
