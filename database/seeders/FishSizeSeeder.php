<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FishSizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [1, '002', '0.200-0.299'],
            [1, '003', '0.300-0.499'],
            [1, '005', '0.500-0.999'],
            [1, '010', '1.000-1.799'],
            [1, '018', '1.800-3.499'],
            [1, '035', '3.500-9.999'],
            [1, '100', '10.000-UP'],

            [2, '002', '0.200-0.299'],
            [2, '003', '0.300-0.499'],
            [2, '005', '0.500-0.999'],
            [2, '010', '1.000-1.799'],
            [2, '018', '1.800-3.499'],
            [2, '035', '3.500-9.999'],
            [2, '100', '10.000-UP'],

            [3, '002', '0.200-0.299'],
            [3, '003', '0.300-0.499'],
            [3, '005', '0.500-0.999'],
            [3, '010', '1.000-1.799'],
            [3, '018', '1.800-3.499'],
            [3, '035', '3.500-9.999'],
            [3, '100', '10.000-19.999'],
            [3, '200', '20.000-UP'],

            [4, '002', '0.200-0.299'],
            [4, '003', '0.300-0.499'],
            [4, '005', '0.500-0.999'],
            [4, '010', '1.000-1.799'],
            [4, '018', '1.800-3.499'],
            [4, '035', '3.500-9.999'],
            [4, '100', '10.000-19.999'],
            [4, '200', '20.000-UP'],
        ];

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'fish_id' => $item[0],
                'code' => $item[1],
                'size_range' => $item[2],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('fish_sizes')->insert($data);
    }
}
