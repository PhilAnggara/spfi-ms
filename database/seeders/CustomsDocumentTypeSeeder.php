<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CustomsDocumentTypeSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $legacyRows = $this->resolveRows('customs_document_type', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource('customs_document_type', 'legacy');
            $this->command?->info('ℹ [customs_document_type] rows loaded: ' . count($legacyRows));

            $this->importRows($legacyRows);

            return;
        }

        $this->logImportSource('customs_document_type', 'csv');

        $path = $this->csvPathFor('customs_document_type');
        if (!File::exists($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return;
        }

        $rows = [];
        $skippedInvalidColumns = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
                continue;
            }

            $rows[] = array_combine($header, $row);
        }

        fclose($handle);

        $this->importRows($rows);

        if ($skippedInvalidColumns > 0) {
            $this->command?->warn("⚠ [customs_document_type] skipped invalid column count: {$skippedInvalidColumns}");
        }
    }

    private function importRows(array $rows): void
    {
        $imported = 0;
        $skippedByCode = 0;

        foreach ($rows as $row) {
            $code = $this->normalizeValue($row['Code'] ?? $row['code'] ?? null);

            if ($code === null) {
                $skippedByCode++;
                continue;
            }

            $rawName = $this->normalizeValue($row['BCName'] ?? $row['bcname'] ?? $row['name'] ?? null);
            $bcField = $this->normalizeValue($row['BCfield'] ?? $row['bcfield'] ?? $row['bc_field'] ?? null);
            $name = $rawName === null
                ? $code
                : $this->stripBcFieldPrefixFromName($rawName, $bcField);

            DB::table('customs_document_types')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'bc_field' => $bcField,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $imported++;
        }

        $this->command?->info("✓ [customs_document_type] imported: {$imported}");
        if ($skippedByCode > 0) {
            $this->command?->warn("⚠ [customs_document_type] skipped rows (missing code): {$skippedByCode}");
        }
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || Str::upper($normalized) === 'NULL') {
            return null;
        }

        return $normalized;
    }

    private function stripBcFieldPrefixFromName(string $name, ?string $bcField): string
    {
        $normalizedName = trim($name);

        if ($bcField === null) {
            return $normalizedName;
        }

        $normalizedBcField = trim($bcField);
        if ($normalizedBcField === '') {
            return $normalizedName;
        }

        $prefixPattern = '/^' . preg_quote($normalizedBcField, '/') . '(?=\s|$)/iu';
        if (!preg_match($prefixPattern, $normalizedName)) {
            return $normalizedName;
        }

        $stripped = preg_replace('/^' . preg_quote($normalizedBcField, '/') . '(?=\s|$)\s*/iu', '', $normalizedName);
        $stripped = trim((string) $stripped);

        return $stripped !== '' ? $stripped : $normalizedName;
    }
}
