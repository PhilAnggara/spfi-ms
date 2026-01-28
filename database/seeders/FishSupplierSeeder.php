<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FishSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['1', 'PT UAN'],
            ['4', 'PT JJ'],
            ['A', 'PT ULAM ARMADA NUSANTARA'],
            ['C', 'PT TOLUTUG MARINDO PRATAMA'],
            ['U', 'PT MITRA JAYA SAMUDRA'],
            ['W', 'PT INDO MINA GRASIA'],
            ['T', 'PT MULTIPAR SAPTA TAMA'],
            ['M', 'PT SARI USAHA MANDIRI'],
            ['M2', 'MEYLIN MARINGKA'],
            ['L', 'FITRI TAKALIUANG'],
            ['R', 'RENDY TATUKUDE'],
            ['F', 'PT SHING SHENG FA OCEAN'],
            ['F2', 'PT BINA NUSA MANDIRI PERTIWI'],
            ['F3', 'TISMAR DIAMANA'],
            ['G', 'GUNAWAN'],
            ['G2', 'PT ARTA MINA JAYA'],
            ['H', 'CV MEX BAHARI TUJUH'],
            ['O', 'PT OCEAN MITRA MAS'],
            ['X', 'AMBARA'],
        ];

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'code' => $item[0],
                'name' => $item[1],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('fish_suppliers')->insert($data);
    }
}
