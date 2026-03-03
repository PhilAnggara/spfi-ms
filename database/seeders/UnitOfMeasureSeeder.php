<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UnitOfMeasureSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Coba ambil data legacy jika mode seeding = legacy.
        $legacyRows = $this->resolveRows('uom', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource('uom', 'legacy');
            $this->command?->info('ℹ [uom] rows loaded: ' . count($legacyRows));

            $imported = 0;

            foreach ($legacyRows as $data) {
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

                $imported++;
            }

            $this->command?->info("✓ [uom] imported: {$imported}");

            return;
        }

        $this->logImportSource('uom', 'csv');

        // 1) Ambil file CSV export dari sistem lama.
        $path = $this->csvPathFor('uom');
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

        $imported = 0;
        $skippedInvalidColumns = 0;

        // 3) Import row by row ke table unit_of_measures sesuai mapping.
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
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

            $imported++;
        }

        fclose($handle);

        $this->command?->info("✓ [uom] imported: {$imported}");
        if ($skippedInvalidColumns > 0) {
            $this->command?->warn("⚠ [uom] skipped invalid column count: {$skippedInvalidColumns}");
        }
    }
}
