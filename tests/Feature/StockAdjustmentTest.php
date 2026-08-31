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

it('paginates stock adjustments index with shared list theme and ajax wrapper', function () {
    for ($i = 1; $i <= 21; $i++) {
        StockAdjustment::factory()->create([
            'sa_number' => 'SA-PAGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }

    $firstPage = $this->actingAs($this->user)
        ->get(route('stock-adjustments.index'))
        ->assertOk();

    $html = $firstPage->getContent();
    $containerPos = strpos($html, 'id="sa-page-container"');
    $resultsPos = strpos($html, 'id="sa-page-results"');

    expect($containerPos)->not->toBeFalse()
        ->and($resultsPos)->not->toBeFalse()
        ->and($containerPos)->toBeLessThan($resultsPos);

    $firstPage
        ->assertSee('SA-PAGE-021')
        ->assertSee('SA-PAGE-002')
        ->assertDontSee('SA-PAGE-001')
        ->assertSee('id="sa-filter-form"', false)
        ->assertSee('list-filter-grid', false)
        ->assertSee('list-table', false)
        ->assertSee('list-pagination', false)
        ->assertSee('stock-adjustments-modern.js', false);

    $this->actingAs($this->user)
        ->get(route('stock-adjustments.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('SA-PAGE-001')
        ->assertDontSee('SA-PAGE-021');
});

it('filters stock adjustments index by keyword', function () {
    StockAdjustment::factory()->create([
        'sa_number' => 'SA-FILTER-ALPHA',
        'reason' => 'Alpha reason',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    StockAdjustment::factory()->create([
        'sa_number' => 'SA-FILTER-BETA',
        'reason' => 'Beta reason',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('stock-adjustments.index', ['keyword' => 'ALPHA']))
        ->assertOk()
        ->assertSee('SA-FILTER-ALPHA')
        ->assertDontSee('SA-FILTER-BETA');
});

it('filters stock adjustments index by date range', function () {
    StockAdjustment::factory()->create([
        'sa_number' => 'SA-DATE-MARCH',
        'sa_date' => '2026-03-15',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    StockAdjustment::factory()->create([
        'sa_number' => 'SA-DATE-APRIL',
        'sa_date' => '2026-04-10',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('stock-adjustments.index', [
            'date_start' => '2026-03-01',
            'date_end' => '2026-03-31',
        ]))
        ->assertOk()
        ->assertSee('SA-DATE-MARCH')
        ->assertDontSee('SA-DATE-APRIL');
});
