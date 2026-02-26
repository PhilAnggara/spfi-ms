<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ItemSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Coba ambil data legacy jika mode seeding = legacy.
        $legacyRows = $this->resolveRows('product', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource('product', 'legacy');

            $uomByName = DB::table('unit_of_measures')->pluck('id', 'name');
            $categoryByName = DB::table('item_categories')->pluck('id', 'name');

            foreach ($legacyRows as $data) {
                $uomName = $data['uom_name'] ?? null;
                $categoryName = $data['product_category'] ?? null;

                $unitId = $uomName !== null ? ($uomByName[$uomName] ?? null) : null;
                $categoryId = $categoryName !== null ? ($categoryByName[$categoryName] ?? null) : null;

                if (!$unitId || !$categoryId) {
                    continue;
                }

                $createdDate = trim((string) ($data['created_date'] ?? ''));
                $updatedDate = trim((string) ($data['updated_date'] ?? ''));

                $createdAt = $createdDate === '' || Str::upper($createdDate) === 'NULL'
                    ? now()
                    : Carbon::parse($createdDate);
                $updatedAt = $updatedDate === '' || Str::upper($updatedDate) === 'NULL'
                    ? now()
                    : Carbon::parse($updatedDate);

                $type = $data['type'] ?? null;
                if ($type === 'NULL') {
                    $type = null;
                }

                DB::table('items')->insert([
                    'name' => $data['product_name'] ?? null,
                    'code' => $data['product_code'] ?? null,
                    'unit_of_measure_id' => $unitId,
                    'category_id' => $categoryId,
                    'type' => $type,
                    'stock_on_hand' => 0,
                    'is_active' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
            }

            return;
        }

        $this->logImportSource('product', 'csv');

        // 1) Ambil file CSV export dari sistem lama.
        $path = $this->csvPathFor('product');
        if (!File::exists($path)) {
            return;
        }

        // 2) Siapkan mapping master dari nama -> id untuk relasi.
        $uomByName = DB::table('unit_of_measures')->pluck('id', 'name');
        $categoryByName = DB::table('item_categories')->pluck('id', 'name');

        // 3) Baca header agar mapping kolom lebih aman jika urutan berubah.
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return;
        }

        // 4) Import row by row ke table items sesuai mapping.
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $uomName = $data['uom_name'] ?? null;
            $categoryName = $data['product_category'] ?? null;

            $unitId = $uomName !== null ? ($uomByName[$uomName] ?? null) : null;
            $categoryId = $categoryName !== null ? ($categoryByName[$categoryName] ?? null) : null;

            if (!$unitId || !$categoryId) {
                continue;
            }

            $createdDate = trim((string) ($data['created_date'] ?? ''));
            $updatedDate = trim((string) ($data['updated_date'] ?? ''));

            $createdAt = $createdDate === '' || Str::upper($createdDate) === 'NULL'
                ? now()
                : Carbon::parse($createdDate);
            $updatedAt = $updatedDate === '' || Str::upper($updatedDate) === 'NULL'
                ? now()
                : Carbon::parse($updatedDate);

            $type = $data['type'] ?? null;
            if ($type === 'NULL') {
                $type = null;
            }

            DB::table('items')->insert([
                'name' => $data['product_name'] ?? null,
                'code' => $data['product_code'] ?? null,
                'unit_of_measure_id' => $unitId,
                'category_id' => $categoryId,
                'type' => $type,
                'stock_on_hand' => 0,
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }

        fclose($handle);
    }
}
