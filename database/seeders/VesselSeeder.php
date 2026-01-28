<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VesselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            ['ts', 'test'],
            ['6', 'none'],
            ['23', 'KM PUTRA SUKSES MANDIRI 9'],
            ['3', 'PERINDO MAJU 03'],
            ['2', 'PERINDO MAJU 04'],
            ['4', 'REFINAY 02'],
            ['5', 'TRANS MITRAMAS 5'],
            ['7', 'PERINDO MAJU 05'],
            ['9', 'KM SUMBER ANUGERAH '],
            ['10', 'KM MICKEY 112'],
            ['11', 'KM BINTANG SUMBER MAS V'],
            ['13', 'SUMBER ANUGERAH 2'],
            ['14', 'LA GRASIA'],
            ['15', 'MINA KENCANA 03'],
            ['16', 'MULIA MAJU JAYA'],
            ['17', 'SARI SEGARA'],
            ['18', 'MICKEY 213'],
            ['19', 'KM BINTANG SAMPURNA JAYA'],
            ['20', 'KM PUTRA SUKSES MANDIRI A'],
            ['21', 'KM LA GRACIA 04'],
            ['24', 'KM GABUNGAN JAYA MINA'],
            ['8', 'MITRA BAHARI 09'],
            ['12', 'LA GRASIA 5'],
            ['22', 'KM MITRA BAHARI 18'],
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

        DB::table('vessels')->insert($data);
    }
}
