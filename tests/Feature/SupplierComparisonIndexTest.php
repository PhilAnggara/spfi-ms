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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Purchasing',
        'code' => '7101',
        'alias' => 'PUR',
    ]);

    $this->manager = User::query()->create([
        'name' => 'Purchasing Manager',
        'username' => 'sc-index-manager',
        'email' => 'sc-index-manager@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->manager->assignRole('purchasing-manager');

    $this->canvasser = User::query()->create([
        'name' => 'Canvasser Staff',
        'username' => 'sc-index-canvasser',
        'email' => 'sc-index-canvasser@example.test',
        'password' => Hash::make('password'),
        'department_id' => $this->department->id,
        'role' => 'Staff',
    ]);
    $this->canvasser->assignRole('purchasing-staff');

    $this->creator = User::query()->create([
        'name' => 'PRS Creator',
        'username' => 'sc-index-creator',
        'email' => 'sc-index-creator@example.test',
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

    $matchingItem = Item::query()->create([
        'name' => 'Matching Comparison Item',
        'code' => 'MATCH-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $otherItem = Item::query()->create([
        'name' => 'Other Comparison Item',
        'code' => 'OTHER-ITEM-001',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'type' => 'Consumable',
        'stock_on_hand' => 10,
        'is_active' => true,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Index Supplier',
        'code' => 'SUP-IDX-001',
        'created_by' => $this->canvasser->id,
    ]);

    $matchingPrs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Matching comparison PRS',
        'status' => 'CANVASSING',
    ]);

    $otherPrs = Prs::query()->create([
        'prs_number' => '7101'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'user_id' => $this->creator->id,
        'department_id' => $this->department->id,
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'is_capex' => false,
        'remarks' => 'Other comparison PRS',
        'status' => 'CANVASSING',
    ]);

    $this->matchingPrsItem = PrsItem::query()->create([
        'prs_id' => $matchingPrs->id,
        'item_id' => $matchingItem->id,
        'quantity' => 5,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    $this->otherPrsItem = PrsItem::query()->create([
        'prs_id' => $otherPrs->id,
        'item_id' => $otherItem->id,
        'quantity' => 3,
        'canvasser_id' => $this->canvasser->id,
        'assigned_canvasser_at' => now(),
        'is_direct_purchase' => false,
    ]);

    foreach ([$this->matchingPrsItem, $this->otherPrsItem] as $prsItem) {
        PrsCanvassingItem::query()->create([
            'prs_id' => $prsItem->prs_id,
            'prs_item_id' => $prsItem->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 1500,
            'lead_time_days' => 7,
            'term_of_payment_type' => 'cash',
            'canvased_by' => $this->canvasser->id,
        ]);
    }
});

it('keeps the keyword input outside the ajax results region', function () {
    $html = $this->actingAs($this->manager)
        ->get(route('procurement.supplier-comparison.index'))
        ->assertOk()
        ->getContent();

    $keywordPos = strpos($html, 'id="supplier-comparison-keyword"');
    $resultsPos = strpos($html, 'id="supplier-comparison-page-results"');

    expect($keywordPos)->not->toBeFalse()
        ->and($resultsPos)->not->toBeFalse()
        ->and($keywordPos)->toBeLessThan($resultsPos);
});

it('filters supplier comparison rows by keyword and fills the search input', function () {
    $response = $this->actingAs($this->manager)
        ->get(route('procurement.supplier-comparison.index', [
            'keyword' => 'MATCH-ITEM-001',
        ]));

    $response->assertOk()
        ->assertSee('id="supplier-comparison-keyword"', false)
        ->assertSee('value="MATCH-ITEM-001"', false)
        ->assertSee('MATCH-ITEM-001')
        ->assertSee('Matching Comparison Item')
        ->assertDontSee('OTHER-ITEM-001')
        ->assertDontSee('Other Comparison Item');
});
