<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            ['477', 'AZKO', 'MANADO', '085340330616', '', '', '', ''],
            ['427', 'UD CAHAYA', 'BITUNG', '', '', '', '', ''],
            ['8', 'ANEKA ELEKTRO CV.', 'JL. SAM RATULANGI 21 No.32, -', '', '', '', '', ''],
            ['9', 'ANEKA GAS INDUSTRI PT.', 'JL. RAYA MANADO - BITUNG, SAGERAT', '', '', '', '', ''],
            ['604', 'SUARA ELECTRO BITUNG', 'BITUNG', '', '', '', '', ''],
            ['573', 'CV ESA GENANG', 'B ITUNG', '085341615541', '', 'esagenang@yahoo.com', '', ''],
            ['346', 'AREMA TEKNIK', 'KEL.PATETEN BITUNG', '', '', '', '', ''],
            ['6', 'ARENA TEKNIK POMPA', 'SURABAYA', '', '', '', '', ''],
            ['15', 'ANUGRAH JAYA BOX', 'DS NGABETAN NO 2 RT001 RW04 CERME GRESIK', '031 7994774', '7994771', '', '', ''],
            ['509', 'PT HALIM SARANA CAHAYA SE', 'SURABAYA', '0317388322', '0317388329', '', '', ''],
        ];

        foreach ($suppliers as $index => $supplier) {
            DB::table('suppliers')->insert ([
                'code' => $supplier[0] ,
                'name' => $supplier[1],
                'address' => $supplier[2],
                'phone' => $supplier[3],
                'fax' => $supplier[4],
                'email' => $supplier[5],
                'contact_person' => $supplier[6],
                'remarks' => $supplier[7],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
