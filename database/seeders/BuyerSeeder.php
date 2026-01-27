<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuyerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['AM GROUP FOR IMPPORT & COMMERCIAL DEALERSHIP', '#1 RING ROAD INTERSECTION, WITH EL TROLY ST. EL SALAM CATY CAIRO EGYPT'],
            ['DAR AL SALAM FOR IMPORT', '1 ST FLOOR EL TOWER IN FRONT OF FACULTY OF LAW MASOURA CITY EGYPT'],
            ['MITSUI & CO ALFAYROUZ CO. FOR TRADE', 'INDUSTERIAL ZONE-WESTERN EXTENTION AREA 12 BOLCK 20017 EL OUBOR CITY-EGYPT '],
            ['CAMERICAN INTERNATIONAL INC', '45 EINSEHOWER DEIVE PARAMUS NEW JERSEY 07652'],
            ['PORT ROYAL SALES CO.LTD', '95 FROELICH FARM BLVD, WOODBURY NEW YORK 11797 USA'],
            ['REMA FOODS', '140 SYLAN AVE, ENGLEWOOD CLIFFS 07632, USA'],
            ['KAWASHO FOODS', 'OFFOCE NO.LB16-201, JEBEL ELI, DUABI, U. A.  E'],
            ['FRERES DELHAIZE ET CLE S.A (VIA AMS)', 'BROEKOOL 210 1731 ZELIK BELGUM'],
            ['MESSRS AMATI FOOD TRADE 1 SRL', 'STRADA DELLA ROMAGNA 77-79, 61012 GRADARA 9PESARO0 / ITALY'],
            ['MESSRS ARTISFOOD SRL', 'PLAZE TRIESTE 1, 27049 STRADELLA (PV) / ITALY'],
        ];

        foreach ($items as $item) {
            DB::table('buyers')->insert ([
                'name' => $item[0],
                'address' => $item[1],
                'created_by' => 1, // Assuming the admin user has ID 1
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
