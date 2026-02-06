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
        //     'alias' => 'IT'
        // ]);
        // Department::create([
        //     'name' => '',
        //     'alias' => 'HR'
        // ]);

        // Department::insert([
        //     ['name' => 'Human Resource', 'alias' => 'HR'],
        //     ['name' => 'Finance', 'alias' => 'FIN'],
        // ]);

        DB::table('departments')->insert([
            [
                'name' => 'Information Technology',
                'code' => '7056',
                'alias' => 'IT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Management',
                'code' => '0001',
                'alias' => 'MGT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Human Resource Development',
                'code' => '7048',
                'alias' => 'HR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Finance',
                'code' => '7052',
                'alias' => 'FIN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Purchasing',
                'code' => '7050',
                'alias' => 'PUR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing / Export',
                'code' => '7036',
                'alias' => 'MKT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Labeling',
                'code' => '7040',
                'alias' => 'FLB',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Inventory Management',
                'code' => '7042',
                'alias' => 'IM',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Quality Assurance',
                'code' => '7044',
                'alias' => 'QA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Engineering',
                'code' => '7046',
                'alias' => 'ENG',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
