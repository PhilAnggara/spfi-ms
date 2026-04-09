<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreWithdrawalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $department = Department::query()->create([
            'name' => 'Inventory Management',
            'code' => '7100',
            'alias' => 'IM',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Store Withdrawal Admin',
            'username' => 'store-admin',
            'email' => 'store-admin@example.test',
            'password' => Hash::make('password'),
            'department_id' => $department->id,
            'role' => 'Manager',
        ]);

        $this->admin->assignRole('administrator');
    }

    public function test_store_withdrawal_search_finds_items_with_minor_typos(): void
    {
        [$unit, $category] = $this->createCatalogReferences();

        Item::query()->create([
            'name' => 'Frozen Sardines Premium',
            'code' => 'FS-001',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 10,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Packaging Tape',
            'code' => 'PKG-001',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 8,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('stores-withdrawals.create', ['search' => 'srdines']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Frozen Sardines Premium');
    }

    public function test_store_withdrawal_search_finds_items_from_partial_words_out_of_order(): void
    {
        [$unit, $category] = $this->createCatalogReferences();

        Item::query()->create([
            'name' => 'Tender Fish Fillet',
            'code' => 'TFF-002',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 15,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Fish Meal Binder',
            'code' => 'FMB-003',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 12,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('stores-withdrawals.create', ['search' => 'fill tend']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Tender Fish Fillet');
    }

    public function test_store_withdrawal_search_can_match_item_category_name(): void
    {
        [$unit] = $this->createCatalogReferences();

        $frozenCategory = ItemCategory::query()->create([
            'name' => 'Warehouse Chemicals',
            'code' => 'WHC',
        ]);

        $dryCategory = ItemCategory::query()->create([
            'name' => 'Office Supplies',
            'code' => 'OFF',
        ]);

        Item::query()->create([
            'name' => 'Blue Sanitizer Liquid',
            'code' => 'CHEM-010',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $frozenCategory->id,
            'stock_on_hand' => 10,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Printer Label Roll',
            'code' => 'OFF-011',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $dryCategory->id,
            'stock_on_hand' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('stores-withdrawals.create', ['search' => 'chemical']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Blue Sanitizer Liquid');
    }

    public function test_store_withdrawal_search_can_match_item_name_acronym(): void
    {
        [$unit, $category] = $this->createCatalogReferences();

        Item::query()->create([
            'name' => 'Frozen Sardines Premium',
            'code' => 'ZP-400',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 14,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Fresh Tuna Whole',
            'code' => 'FTW-401',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 9,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('stores-withdrawals.create', ['search' => 'fsp']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Frozen Sardines Premium');
    }

    private function createCatalogReferences(): array
    {
        $unit = UnitOfMeasure::query()->create([
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $category = ItemCategory::query()->create([
            'name' => 'Frozen Goods',
            'code' => 'FRZ',
        ]);

        return [$unit, $category];
    }
}
