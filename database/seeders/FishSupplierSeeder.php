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
            ['1', 'RENDY TATUKUDE'],
            ['2', 'PT MULTIPAR SAPTA TAMA'],
            ['5', 'MEYLIN MARINGKA'],
            ['12', 'NEVI RAHEL MARASI'],
            ['13', 'IVONE E WAWORUNTU'],
            ['14', 'ANEKE BUDIMAN'],
            ['15', 'JULIUS HENGKENGBALA'],
            ['16', 'PT PERIKANAN NUSANTARA JAYA'],
            ['17', 'I NYOMAN TIMUR JAYA AMBARA'],
            ['18', 'ANEKE BUDIMAN'],
            ['19', 'PT PATHEMAANG RAYA'],
            ['23', 'YANTI MANOY'],
            ['24', 'VENNY SANTOSA'],
            ['25', 'PT TOLUTUG MARINDO PRATAMA'],
            ['27', 'PUE KIU'],
            ['28', 'CV MEX NELAYAN JAYA'],
            ['29', 'JOPPY MASSIE'],
            ['30', 'PT SARI USAHA MANDIRI'],
            ['31', 'PT BUDI SENTOSA ABADI'],
            ['33', 'YUPSTENIK TAKAKOBI'],
            ['3', 'FITRI TAKALIUANG'],
            ['4', 'CANDRAWAN'],
            ['20', 'PT ULAM ARMADA NUSANTARA'],
            ['21', 'PT BINA NUSA MANDIRI PERTIWI'],
            ['22', 'PT BINTANG HARAPAN JAYA'],
            ['26', 'CV MEX BAHARI TUJUH'],
            ['32', 'DARREN ENRICO JOSHUA PALIT'],
            ['6', 'PT MITRA JAYA SAMUDERA'],
            ['7', 'STEVEN SUSANTO SIMON'],
            ['8', 'PT INDO MINA GRASIA'],
            ['9', 'PT OCEAN MITRAMAS'],
            ['10', 'MEX NAFIRI JAYA'],
            ['11', 'GUNAWAN'],
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
