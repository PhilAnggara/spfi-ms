<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['Office Supplies', 'Off Sup'],
            ['Parts', 'Parts'],
            ['Sl/C', 'Sl/C'],
            ['Factory Supplies', 'Fact Sup'],
            ['Chem', 'Chem'],
            ['Fuel', 'Fuel'],
            ['Packaging', 'Pack'],
            ['Label', 'Label'],
            ['Carton', 'Carton'],
            ['Ingredients', 'Ingredients'],
            ['Can', 'Can'],
            ['Spices', 'Spices'],
            ['Others', 'Others'],
            ['Raw Materials', 'Raw Materials'],
            ['52618', '52618'],
            ['Spices And Ingredients', 'Spices And Ingredients'],
            ['Fish', 'Fish'],
            ['BC', 'BC'],
            ['Fishmeal', 'Fishmeal'],
            ['Coal', 'Coal'],
            ['Sludge Oil', 'Sludge Oil'],
            ['Labeling Supplies', 'Labeling Supplies'],
            ['Capital Goods', 'Capital Goods'],
            ['26', '26'],
            ['Finished Goods', 'Finished Goods'],
        ];

        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'name' => $category[0],
                'code' => $category[1],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('item_categories')->insert($data);
    }
}
