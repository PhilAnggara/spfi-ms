<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            DepartmentSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            UnitOfMeasureSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            SupplierSeeder::class,
            PrsSeeder::class,
            PrsItemSeeder::class,
            CurrencySeeder::class,
            BuyerSeeder::class,
        ]);
    }
}
