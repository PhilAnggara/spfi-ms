<?php

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustment;
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
        'code' => '7042-SA',
        'alias' => 'IM-SA',
    ]);

    $this->user = User::query()->create([
        'name' => 'SA User',
        'username' => 'sa-user',
        'email' => 'sa-user@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->user->assignRole('im-staff');

    $unit = UnitOfMeasure::query()->create([
        'name' => 'Pieces',
        'code' => 'PCS-SA',
    ]);

    $category = ItemCategory::query()->create([
        'name' => 'SPARE PARTS',
        'code' => 'SP-SA',
    ]);

    $this->item = Item::query()->create([
        'name' => 'Adjustment Item',
        'code' => 'SA-ITEM-001',
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
});

it('posts an increase stock adjustment to qty_in2', function () {
    $response = $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_number_suggested' => 'SA000001',
        'sa_date' => now()->toDateString(),
        'reason' => 'Correct overstock migration',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 140,
                'wh_code' => 'MAIN',
            ],
        ],
    ]);

    $response->assertRedirect();

    $adjustment = StockAdjustment::query()->first();
    expect($adjustment)->not->toBeNull();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(140.0);
    expect((float) $this->item->fresh()->stock_on_hand)->toBe(140.0);

    $ledger = StockBalance::query()
        ->where('reference_type', StockService::REF_STOCK_ADJUSTMENT)
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->qty_in2)->toBe(40.0)
        ->and((float) $ledger->qty_out2)->toBe(0.0)
        ->and((float) $ledger->end)->toBe(140.0);
});

it('posts a decrease stock adjustment to qty_out2', function () {
    $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_date' => now()->toDateString(),
        'reason' => 'Reduce stock',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 70,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertRedirect();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(70.0);

    $ledger = StockBalance::query()
        ->where('reference_type', StockService::REF_STOCK_ADJUSTMENT)
        ->first();

    expect((float) $ledger->qty_out2)->toBe(30.0)
        ->and((float) $ledger->qty_in2)->toBe(0.0);
});

it('reverses stock when adjustment is deleted', function () {
    $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_date' => now()->toDateString(),
        'reason' => 'Temp adjust',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 125,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertRedirect();

    $adjustment = StockAdjustment::query()->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);
    expect(StockAdjustment::query()->count())->toBe(0);
});

it('rejects zero-delta adjustments', function () {
    $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_date' => now()->toDateString(),
        'reason' => 'No change',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 100,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertSessionHasErrors('items');
});

it('forbids purchasing staff from creating stock adjustments', function () {
    $outsider = User::query()->create([
        'name' => 'Purchasing SA Outsider',
        'username' => 'purchasing-sa-outsider',
        'email' => 'purchasing-sa-outsider@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $outsider->assignRole('purchasing-staff');

    $this->actingAs($outsider)->post(route('stock-adjustments.store'), [
        'sa_date' => now()->toDateString(),
        'reason' => 'Should fail',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 110,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertForbidden();
});

it('searches items by code with balance and limits results', function () {
    Item::query()->create([
        'name' => 'Other Spare',
        'code' => 'ZZ-OTHER-001',
        'unit_of_measure_id' => $this->item->unit_of_measure_id,
        'category_id' => $this->item->category_id,
        'type' => 'Spare Parts',
        'stock_on_hand' => 0,
        'is_active' => true,
    ]);

    $short = $this->actingAs($this->user)->getJson(route('stock-adjustments.items.search', ['q' => 'S']));
    $short->assertSuccessful();
    expect($short->json('items'))->toBe([]);

    $response = $this->actingAs($this->user)->getJson(route('stock-adjustments.items.search', [
        'q' => 'SA-ITEM',
    ]));

    $response->assertSuccessful();
    $items = $response->json('items');
    expect($items)->toHaveCount(1)
        ->and($items[0]['code'])->toBe('SA-ITEM-001')
        ->and((float) $items[0]['balance'])->toBe(100.0);
});

it('loads create page without embedding full item catalog', function () {
    $response = $this->actingAs($this->user)->get(route('stock-adjustments.create'));

    $response->assertSuccessful();
    $response->assertDontSee('data-items=', false);
    $response->assertSee('data-search-url', false);
});
