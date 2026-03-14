<?php

namespace Database\Seeders;

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
        $startedAt = microtime(true);

        // User::factory(10)->create();

        $this->call([
            DepartmentSeeder::class,
            EmployeeDepartmentSeeder::class,
            EmployeeSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            UnitOfMeasureSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            SupplierSeeder::class,
            CustomsDocumentTypeSeeder::class,
            PrsSeeder::class,
            PrsItemSeeder::class,
            CurrencySeeder::class,
            PurchaseOrderSeeder::class,
            ReceivingReportSeeder::class,
            StockInventorySeeder::class,
            StockBalanceSeeder::class,
            StoreWithdrawalSeeder::class,
            TransferSlipSeeder::class,
            BuyerSeeder::class,
            FishSupplierSeeder::class,
            VesselSeeder::class,
            BatchSeeder::class,
            FishSeeder::class,
            FishSizeSeeder::class,
            AccountingDataSeeder::class,
        ]);

        $elapsedSeconds = (int) round(microtime(true) - $startedAt);
        $hours = intdiv($elapsedSeconds, 3600);
        $minutes = intdiv($elapsedSeconds % 3600, 60);
        $seconds = $elapsedSeconds % 60;
        $formattedDuration = sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);

        if ($this->command) {
            $this->command->info('Total seeding time: '.$formattedDuration);
        }
    }
}
