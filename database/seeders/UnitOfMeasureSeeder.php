<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['MT', 'MT',],
            ['PCS', 'PCS',],
            ['PAIR', 'PAIR',],
            ['LOT', 'LOT',],
            ['KGS', 'KGS',],
            ['SET', 'SET',],
            ['BOX', 'BOX',],
            ['UNIT', 'UNIT',],
            ['TIN', 'TIN',],
            ['BTL', 'BTL',],
            ['PC', 'PC',],
            ['ROLL', 'ROLL',],
            ['PACK', 'PACK',],
            ['LE', 'LE',],
            ['SHEET', 'SHEET',],
            ['m3', 'm3',],
            ['0', '0',],
            ['MTR', 'MTR',],
            ['GLN', 'GLN',],
            ['TUBE', 'TUBE',],
            ['SACK', 'SACK',],
            ['LTR', 'LTR',],
            ['LGTS', 'LGTS',],
            ['DOS', 'DOS',],
            ['PAILS', 'PAILS',],
            ['GRAMS', 'GRAMS',],
            ['LUSIN', 'LUSIN',],
            ['TBG', 'TBG',],
            ['ROLLS', 'ROLLS',],
            ['SCHT', 'SCHT',],
            ['MTRS', 'MTRS',],
            ['TRUCK', 'TRUCK',],
            ['DOSS', 'DOSS',],
            ['SETS', 'SETS',],
            ['TINS', 'TINS',],
            ['BOXES', 'BOXES',],
            ['PART', 'PART',],
            ['PADS', 'PADS',],
            ['REAMS', 'REAMS',],
            ['KALENG', 'KALENG',],
            ['SHTS', 'SHTS',],
            ['SHEETS', 'SHEETS',],
            ['BAGS', 'BAGS',],
            ['UJUNG', 'UJUNG',],
            ['LEMBAR', 'LEMBAR',],
            ['UNITS', 'UNITS',],
            ['PAIRS', 'PAIRS',],
            ['CYL', 'CYL',],
            ['QUART', 'QUART',],
            ['M2', 'M2',],
            ['TUBES', 'TUBES',],
            ['truk', 'truk',],
            ['TRIP', 'TRIP',],
            ['METERS', 'METERS',],
            ['TBNG', 'TBNG',],
            ['DRUM', 'DRUM',],
            ['TIMES', 'TIMES',],
            ['BTG', 'BTG',],
            ['BUNDLES', 'BUNDLES',],
            ['HOUR', 'HOUR',],
            ['Ea', 'Ea',],
            ['RIM', 'RIM',],
            ['MB', 'MB',],
            ['CASE', 'CASE',],
            ['SACKS', 'SACKS',],
            ['CM', 'CM',],
            ['POTS', 'POTS',],
            ['LITER', 'LITER',],
            ['POHON', 'POHON',],
            ['PACKS', 'PACKS',],
            ['METER', 'METER',],
            ['SHETS', 'SHETS',],
            ['STRIP', 'STRIP',],
            ['PAIL', 'PAIL',],
            ['OCS', 'OCS',],
            ['GB', 'GB',],
            ['MBPS', 'MBPS',],
            ['LITERS', 'LITERS',],
            ['KLG', 'KLG',],
            ['BKS', 'BKS',],
            ['DOZ', 'DOZ',],
            ['PCA', 'PCA',],
            ['KG', 'KG',],
            ['TRAY', 'TRAY',],
            ['ROLSS', 'ROLSS',],
            ['WEEK', 'WEEK',],
            ['FEET', 'FEET',],
            ['PRINTED', 'PRINTED',],
            ['CRTG', 'CRTG',],
            ['LENGHT', 'LENGTH',],
            ['11', 'USER',],
        ];

        foreach ($units as $unit) {
            DB::table('unit_of_measures')->insert([
                'code' => $unit[0],
                'name' => $unit[1],
                'remarks' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
