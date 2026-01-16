<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Department::create([
        //     'name' => 'IT',
        //     'code' => 'IT01'
        // ]);
        // Department::create([
        //     'name' => '',
        //     'code' => 'HR01'
        // ]);

        // Department::insert([
        //     ['name' => 'Human Resource', 'code' => 'HR'],
        //     ['name' => 'Finance', 'code' => 'FIN'],
        // ]);

        DB::table('departments')->insert([
            [
                'name' => 'Information Technology',
                'code' => 'IT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Human Resource',
                'code' => 'HR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing',
                'code' => 'MKT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
