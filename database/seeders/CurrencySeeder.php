<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['IDR','Rupiah','Rp'],
            ['PHP','Peso','PHP'],
            ['EUR','Euro','&euro;'],
            ['GBP','Pound Sterling','£'],
            ['USD','US Dollar','$'],
            ['JPY','Yen ','¥'],
            ['SGD','Dollar Singapura','SGD'],
            ['CNY','Chinese Yuan','CNY'],
        ];

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'name' => $item[0],
                'code' => $item[1],
                'symbol' => $item[2],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('currencies')->insert($data);
    }
}
