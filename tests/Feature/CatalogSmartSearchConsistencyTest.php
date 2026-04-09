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

class CatalogSmartSearchConsistencyTest extends TestCase
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
            'name' => 'Catalog Search Admin',
            'username' => 'catalog-admin',
            'email' => 'catalog-admin@example.test',
            'password' => Hash::make('password'),
            'department_id' => $department->id,
            'role' => 'Manager',
        ]);

        $this->admin->assignRole('administrator');
    }

    public function test_prs_create_uses_the_same_typo_tolerant_catalog_search(): void
    {
        [$unit, $category] = $this->createCatalogReferences();

        Item::query()->create([
            'name' => 'Frozen Sardines Premium',
            'code' => 'FSP-001',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 18,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Corrugated Box Large',
            'code' => 'CBL-010',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $category->id,
            'stock_on_hand' => 7,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('prs.create', ['search' => 'srdines']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Frozen Sardines Premium');
    }

    public function test_delivery_create_uses_the_same_category_aware_catalog_search(): void
    {
        $unit = UnitOfMeasure::query()->create([
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $chemicalCategory = ItemCategory::query()->create([
            'name' => 'Warehouse Chemicals',
            'code' => 'WHC',
        ]);

        $officeCategory = ItemCategory::query()->create([
            'name' => 'Office Supplies',
            'code' => 'OFF',
        ]);

        Item::query()->create([
            'name' => 'Blue Sanitizer Liquid',
            'code' => 'CHEM-010',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $chemicalCategory->id,
            'stock_on_hand' => 10,
            'is_active' => true,
        ]);

        Item::query()->create([
            'name' => 'Printer Label Roll',
            'code' => 'OFF-011',
            'unit_of_measure_id' => $unit->id,
            'category_id' => $officeCategory->id,
            'stock_on_hand' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('deliveries.create', ['search' => 'chemical']));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Blue Sanitizer Liquid');
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
