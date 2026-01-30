<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('items')->insert([
            [
                'code' => 'ELC0001',
                'name' => 'Kompter PC ASUS',
                'unit_of_measure_id' => 6,
                'category_id' => 1,
                'type' => 'Capital Goods',
                'stock_on_hand' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ELC0002',
                'name' => 'Keyboard Logitech',
                'unit_of_measure_id' => 2,
                'category_id' => 1,
                'type' => 'Capital Goods',
                'stock_on_hand' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'FUR0001',
                'name' => 'Kursi Kantor Ergonomic',
                'unit_of_measure_id' => 2,
                'category_id' => 1,
                'type' => 'Capital Goods',
                'stock_on_hand' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'FUR0002',
                'name' => 'Meja Kantor Kayu',
                'unit_of_measure_id' => 6,
                'category_id' => 1,
                'type' => 'Capital Goods',
                'stock_on_hand' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
