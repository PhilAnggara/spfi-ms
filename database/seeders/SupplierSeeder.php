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
        $csvPath = base_path('public/document/csv/supplier.csv');

        if (!file_exists($csvPath)) {
            $this->command?->warn("supplier.csv not found at: {$csvPath}");
            return;
        }

        $normalize = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim($value);
            if ($value === '' || strtoupper($value) === 'NULL') {
                return null;
            }

            return $value;
        };

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command?->warn("Failed to open supplier.csv at: {$csvPath}");
            return;
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            $this->command?->warn("supplier.csv is empty: {$csvPath}");
            return;
        }

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 8) {
                continue;
            }

            $isActive = strtoupper($normalize($row[8] ?? null) ?? '');
            $isDeleted = strtoupper($normalize($row[9] ?? null) ?? '');
            if ($isActive !== 'Y' || $isDeleted !== 'N') {
                continue;
            }

            $code = $normalize($row[1] ?? null);
            if ($code === null) {
                continue;
            }

            DB::table('suppliers')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $normalize($row[2] ?? null) ?? '',
                    'address' => $normalize($row[3] ?? null),
                    'phone' => $normalize($row[4] ?? null),
                    'fax' => $normalize($row[5] ?? null),
                    'email' => $normalize($row[6] ?? null),
                    'contact_person' => $normalize($row[7] ?? null),
                    'remarks' => null,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        fclose($handle);
    }
}
