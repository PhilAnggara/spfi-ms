<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ItemCategorySeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Coba ambil data legacy jika mode seeding = legacy.
        $legacyRows = $this->resolveRows('product_category', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource('product_category', 'legacy');
            $this->command?->info('ℹ [product_category] rows loaded: ' . count($legacyRows));

            $imported = 0;

            foreach ($legacyRows as $data) {
                $createdDate = trim((string) ($data['created_date'] ?? ''));
                $updatedDate = trim((string) ($data['updated_date'] ?? ''));

                $createdAt = $createdDate === '' || Str::upper($createdDate) === 'NULL'
                    ? now()
                    : Carbon::parse($createdDate);
                $updatedAt = $updatedDate === '' || Str::upper($updatedDate) === 'NULL'
                    ? now()
                    : Carbon::parse($updatedDate);

                DB::table('item_categories')->insert([
                    'name' => $data['category_name'] ?? null,
                    'code' => $data['category_code'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                $imported++;
            }

            $this->command?->info("✓ [product_category] imported: {$imported}");

            return;
        }

        $this->logImportSource('product_category', 'csv');

        // 1) Ambil file CSV export dari sistem lama.
        $path = $this->csvPathFor('product_category');
        if (!File::exists($path)) {
            return;
        }

        // 2) Baca header untuk mapping kolom.
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return;
        }

        $imported = 0;
        $skippedInvalidColumns = 0;

        // 3) Import row by row ke table item_categories sesuai mapping.
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
                continue;
            }

            $data = array_combine($header, $row);

            $createdDate = trim((string) ($data['created_date'] ?? ''));
            $updatedDate = trim((string) ($data['updated_date'] ?? ''));

            $createdAt = $createdDate === '' || Str::upper($createdDate) === 'NULL'
                ? now()
                : Carbon::parse($createdDate);
            $updatedAt = $updatedDate === '' || Str::upper($updatedDate) === 'NULL'
                ? now()
                : Carbon::parse($updatedDate);

            DB::table('item_categories')->insert([
                'name' => $data['category_name'] ?? null,
                'code' => $data['category_code'] ?? null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $imported++;
        }

        fclose($handle);

        $this->command?->info("✓ [product_category] imported: {$imported}");
        if ($skippedInvalidColumns > 0) {
            $this->command?->warn("⚠ [product_category] skipped invalid column count: {$skippedInvalidColumns}");
        }
    }
}
