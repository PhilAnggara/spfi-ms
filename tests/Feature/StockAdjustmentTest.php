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
    $originalNumber = $adjustment->sa_number;

    $this->actingAs($this->user)
        ->delete(route('stock-adjustments.destroy', $adjustment))
        ->assertRedirect(route('stock-adjustments.index'));

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);
    expect(StockAdjustment::query()->count())->toBe(0);

    $trashed = StockAdjustment::withTrashed()->find($adjustment->id);
    expect($trashed)->not->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->sa_number)->toBe('DELETED-'.$adjustment->id);

    app(\App\Services\DocumentNumberService::class)->assertUnique('SA', $originalNumber);
});

it('reuses sa number after delete and reverse', function () {
    $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_number' => '000007',
        'sa_number_suggested' => '000001',
        'sa_date' => now()->toDateString(),
        'reason' => 'First SA',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 110,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertRedirect();

    $first = StockAdjustment::query()->where('sa_number', '000007')->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('stock-adjustments.destroy', $first))
        ->assertRedirect();

    expect((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);

    $this->actingAs($this->user)->post(route('stock-adjustments.store'), [
        'sa_number' => '000007',
        'sa_number_suggested' => '000001',
        'sa_date' => now()->toDateString(),
        'reason' => 'Reuse SA number',
        'confirmed' => '1',
        'items' => [
            [
                'item_id' => $this->item->id,
                'new_balance' => 105,
                'wh_code' => 'MAIN',
            ],
        ],
    ])->assertRedirect();

    expect(StockAdjustment::query()->where('sa_number', '000007')->count())->toBe(1)
        ->and((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(105.0);
});

it('rejects a duplicate sa number with validation error instead of 500', function () {
    StockAdjustment::query()->create([
        'sa_number' => 'SA-TAKEN-001',
        'sa_date' => now()->toDateString(),
        'reason' => 'Existing',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->from(route('stock-adjustments.create'))
        ->post(route('stock-adjustments.store'), [
            'sa_number' => 'SA-TAKEN-001',
            'sa_number_suggested' => '000001',
            'sa_date' => now()->toDateString(),
            'reason' => 'Duplicate attempt',
            'confirmed' => '1',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'new_balance' => 120,
                    'wh_code' => 'MAIN',
                ],
            ],
        ])
        ->assertRedirect(route('stock-adjustments.create'))
        ->assertSessionHasErrors('sa_number');

    expect(StockAdjustment::query()->where('sa_number', 'SA-TAKEN-001')->count())->toBe(1)
        ->and((float) StockInventory::query()->where('item_id', $this->item->id)->value('balance'))->toBe(100.0);
});

it('rejects sa number still held by a soft-deleted row that was not released', function () {
    $stale = StockAdjustment::query()->create([
        'sa_number' => 'SA-STALE-001',
        'sa_date' => now()->toDateString(),
        'reason' => 'Stale soft delete',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);
    $stale->delete();

    expect(StockAdjustment::query()->whereKey($stale->id)->exists())->toBeFalse();
    expect(StockAdjustment::withTrashed()->find($stale->id)?->sa_number)->toBe('SA-STALE-001');

    $this->actingAs($this->user)
        ->from(route('stock-adjustments.create'))
        ->post(route('stock-adjustments.store'), [
            'sa_number' => 'SA-STALE-001',
            'sa_number_suggested' => '000001',
            'sa_date' => now()->toDateString(),
            'reason' => 'Should fail validation',
            'confirmed' => '1',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'new_balance' => 120,
                    'wh_code' => 'MAIN',
                ],
            ],
        ])
        ->assertRedirect(route('stock-adjustments.create'))
        ->assertSessionHasErrors('sa_number');

    expect(app(\App\Services\DocumentNumberService::class)->previewNext('SA'))->not->toBe('SA-STALE-001');
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
