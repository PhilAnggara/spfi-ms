<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1) Ambil file CSV export dari sistem lama.
        $path = public_path('document/csv/uom.csv');
        if (!File::exists($path)) {
            return;
        }

        // 2) Baca header agar mapping kolom lebih aman jika urutan berubah.
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return;
        }

        // 3) Import row by row ke table unit_of_measures sesuai mapping.
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $remarks = trim((string) ($data['remarks'] ?? ''));
            $remarks = $remarks === '' || Str::upper($remarks) === 'NULL' ? null : $remarks;

            $createdDate = trim((string) ($data['created_date'] ?? ''));
            $updatedDate = trim((string) ($data['updated_date'] ?? ''));

            $createdAt = $createdDate === '' || Str::upper($createdDate) === 'NULL'
                ? now()
                : Carbon::parse($createdDate);
            $updatedAt = $updatedDate === '' || Str::upper($updatedDate) === 'NULL'
                ? now()
                : Carbon::parse($updatedDate);

            DB::table('unit_of_measures')->insert([
                'name' => $data['uom_name'] ?? null,
                'code' => $data['uom_code'] ?? null,
                'remarks' => $remarks,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }

        fclose($handle);
    }
}
