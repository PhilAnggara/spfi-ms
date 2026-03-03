<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Coba ambil data legacy jika mode seeding = legacy.
        $legacyRows = $this->resolveRows('supplier', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource('supplier', 'legacy');
            $this->command?->info('ℹ [supplier] rows loaded: ' . count($legacyRows));

            $imported = 0;
            $skippedByCode = 0;
            $finalSoftDeleteByCode = [];

            foreach ($legacyRows as $row) {
                $isActive = strtoupper($this->normalizeValue($this->readSupplierField($row, ['is_active', 'active', 'status_active'])) ?? '');
                $isDeleted = strtoupper($this->normalizeValue($this->readSupplierField($row, ['is_deleted', 'is_delete', 'deleted', 'status_deleted'])) ?? '');
                $shouldSoftDelete = $isActive !== 'Y' || $isDeleted !== 'N';

                $code = $this->normalizeValue($this->readSupplierField($row, ['supplier_code', 'code', 'supp_code', 'vendor_code']));
                if ($code === null) {
                    $skippedByCode++;
                    continue;
                }

                DB::table('suppliers')->updateOrInsert(
                    ['code' => $code],
                    [
                        'name' => $this->normalizeValue($this->readSupplierField($row, ['supplier_name', 'name', 'supp_name'])) ?? '',
                        'address' => $this->normalizeValue($this->readSupplierField($row, ['address', 'supplier_address'])),
                        'phone' => $this->normalizeValue($this->readSupplierField($row, ['phone', 'telp', 'telephone'])),
                        'fax' => $this->normalizeValue($this->readSupplierField($row, ['fax'])),
                        'email' => $this->normalizeValue($this->readSupplierField($row, ['email', 'mail'])),
                        'contact_person' => $this->normalizeValue($this->readSupplierField($row, ['contact_person', 'contact', 'pic'])),
                        'remarks' => null,
                        'created_by' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => $shouldSoftDelete ? now() : null,
                    ]
                );

                $imported++;
                $finalSoftDeleteByCode[$code] = $shouldSoftDelete;
            }

            $importedUnique = count($finalSoftDeleteByCode);
            $softDeleted = count(array_filter($finalSoftDeleteByCode));

            $this->command?->info("✓ Imported {$importedUnique} suppliers (legacy, unique codes)");
            if ($imported !== $importedUnique) {
                $this->command?->warn("⚠ Duplicate supplier codes in source (legacy): " . ($imported - $importedUnique));
            }
            $this->command?->info("ℹ Soft-deleted suppliers (legacy): {$softDeleted}");
            if ($skippedByCode > 0) {
                $this->command?->warn("⚠ Skipped suppliers (legacy) - missing code: {$skippedByCode}");
            }

            return;
        }

        $this->logImportSource('supplier', 'csv');

        $csvPath = $this->csvPathFor('supplier');

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

        $imported = 0;
        $skippedInvalidColumns = 0;
        $skippedByCode = 0;
        $finalSoftDeleteByCode = [];

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 8) {
                $skippedInvalidColumns++;
                continue;
            }

            $isActive = strtoupper($normalize($row[8] ?? null) ?? '');
            $isDeleted = strtoupper($normalize($row[9] ?? null) ?? '');
            $shouldSoftDelete = $isActive !== 'Y' || $isDeleted !== 'N';

            $code = $normalize($row[1] ?? null);
            if ($code === null) {
                $skippedByCode++;
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
                    'deleted_at' => $shouldSoftDelete ? now() : null,
                ]
            );

            $imported++;
            $finalSoftDeleteByCode[$code] = $shouldSoftDelete;
        }

        fclose($handle);

        $importedUnique = count($finalSoftDeleteByCode);
        $softDeleted = count(array_filter($finalSoftDeleteByCode));

        $this->command?->info("✓ Imported {$importedUnique} suppliers (csv, unique codes)");
        if ($imported !== $importedUnique) {
            $this->command?->warn("⚠ Duplicate supplier codes in source (csv): " . ($imported - $importedUnique));
        }
        $this->command?->info("ℹ Soft-deleted suppliers (csv): {$softDeleted}");
        if ($skippedInvalidColumns > 0 || $skippedByCode > 0) {
            $this->command?->warn("⚠ Skipped suppliers (csv) - invalid columns: {$skippedInvalidColumns}, missing code: {$skippedByCode}");
        }
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        return $value;
    }

    private function readSupplierField(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return (string) $row[$key];
            }
        }

        return null;
    }
}
