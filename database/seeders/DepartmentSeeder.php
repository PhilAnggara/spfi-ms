<?php

namespace Database\Seeders;

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
                'name' => 'Office Of The Managing Director',
                'code' => '7054',
                'alias' => 'MD',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sales',
                'code' => '7000',
                'alias' => 'SLS',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Raw Material Used',
                'code' => '7010',
                'alias' => 'RMU',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Ice Plant',
                'code' => '7029',
                'alias' => 'VIP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Ice Plant',
                'code' => '7030',
                'alias' => 'FIP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Fish Procurement',
                'code' => '7031',
                'alias' => 'VFP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Fish Procurement',
                'code' => '7032',
                'alias' => 'FFP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Production',
                'code' => '7033',
                'alias' => 'VP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Production',
                'code' => '7034',
                'alias' => 'FP',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Distribution',
                'code' => '7035',
                'alias' => 'VD',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing/Export',
                'code' => '7036',
                'alias' => 'MKT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Rendering',
                'code' => '7037',
                'alias' => 'VR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Rendering',
                'code' => '7038',
                'alias' => 'FR',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Labeling',
                'code' => '7039',
                'alias' => 'VL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Labeling',
                'code' => '7040',
                'alias' => 'FL',
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
            [
                'name' => 'Human Resources Development',
                'code' => '7048',
                'alias' => 'HRD',
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
                'name' => 'Finance',
                'code' => '7052',
                'alias' => 'FIN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jakarta Office',
                'code' => '7060',
                'alias' => 'JKT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Cold Storage',
                'code' => '7061',
                'alias' => 'VCS',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Cold Storage',
                'code' => '7062',
                'alias' => 'FCS',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Variable Viand Tuna',
                'code' => '7063',
                'alias' => 'VVT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fixed Viand Tuna',
                'code' => '7064',
                'alias' => 'FVT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sanitation',
                'code' => '7033E',
                'alias' => 'SNT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Skinning and Loining',
                'code' => '7033C',
                'alias' => 'SNL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Packing',
                'code' => '7033D',
                'alias' => 'PCK',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Case Up',
                'code' => '7033F',
                'alias' => 'CSU',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Retord',
                'code' => '7033G',
                'alias' => 'RTD',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Others',
                'code' => '8000',
                'alias' => 'OTH',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'BeaCukai',
                'code' => '001',
                'alias' => 'BC',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
