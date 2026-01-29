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
        $item = [
            ['Skipjack', 'SJ'],
            ['Yellowfin', 'YF'],
            ['Bigeye', 'BE'],
            ['Spoilage', 'SPO'],
        ];

        $data = [];
        foreach ($item as $fish) {
            $data[] = [
                'code' => $fish[1],
                'name' => $fish[0],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('fish')->insert($data);
    }
}
