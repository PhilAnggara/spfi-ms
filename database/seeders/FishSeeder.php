<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['Skipjack', 'SJ'],
            ['Bigeye', 'BE'],
            ['Yellowfin', 'YF'],
            ['Albacore', 'AL'],
            ['Spoilage', 'SPO'],
        ];

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'code' => $item[1],
                'name' => $item[0],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('fish')->insert($data);
    }
}
