<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prs')->insert([
            [
                'prs_number' => 'IT-010126-001',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'DRAFT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => 'IT-010126-002',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'DRAFT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => 'HR-010126-001',
                'user_id' => 2,
                'department_id' => 2,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(2),
                'remarks' => 'Penting',
                'status' => 'DRAFT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
