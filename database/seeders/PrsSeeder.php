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
                'prs_number' => '7056-010126-001',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => '7056-010126-002',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => '7050-010126-003',
                'user_id' => 5,
                'department_id' => 5,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(2),
                'remarks' => 'Penting',
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
