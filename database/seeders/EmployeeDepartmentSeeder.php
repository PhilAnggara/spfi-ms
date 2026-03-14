<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeDepartmentSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadRows('employee_department');

        if (empty($rows)) {
            $this->command?->warn('No employee department rows found from configured source.');
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $id = $this->toInteger($row['Id'] ?? $row['id'] ?? null);
            $code = $this->normalizeText($row['DeptCode'] ?? $row['dept_code'] ?? $row['code'] ?? null);
            $name = $this->normalizeText($row['DeptName'] ?? $row['dept_name'] ?? $row['name'] ?? null);

            if ($code === null || $name === null) {
                $skipped++;
                continue;
            }

            $payload = [
                'code' => $code,
                'old_code' => $this->normalizeText($row['OldDeptCode'] ?? $row['old_dept_code'] ?? $row['old_code'] ?? null),
                'name' => $name,
                'is_active' => true,
                'updated_at' => now(),
                'deleted_at' => null,
            ];

            if ($id !== null) {
                DB::table('employee_departments')->updateOrInsert(
                    ['id' => $id],
                    ['created_at' => now()] + $payload
                );
            } else {
                DB::table('employee_departments')->updateOrInsert(
                    ['code' => $code],
                    ['created_at' => now()] + $payload
                );
            }

            $inserted++;
        }

        $this->command?->info("✓ [employee_department] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRows(string $dataset): array
    {
        $legacyRows = $this->resolveRows($dataset, fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && ! empty($legacyRows)) {
            $this->logImportSource($dataset, 'legacy');
            $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($legacyRows));
            return $legacyRows;
        }

        $csvRows = $this->readCsvRows($dataset);

        $this->logImportSource($dataset, $this->isLegacySource() ? 'csv-fallback' : 'csv');
        $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($csvRows));

        return $csvRows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsvRows(string $dataset): array
    {
        $csvPath = $this->csvPathFor($dataset);

        if (! file_exists($csvPath)) {
            $this->command?->warn("CSV for dataset [{$dataset}] not found at {$csvPath}");
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->command?->warn("Unable to open CSV for dataset [{$dataset}] at {$csvPath}");
            return [];
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn ($value) => trim((string) $value), $header);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $row = array_pad($row, count($header), null);
            $combined = array_combine($header, array_slice($row, 0, count($header)));

            if ($combined === false) {
                continue;
            }

            $rows[] = $combined;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' || strtoupper($normalized) === 'NULL'
            ? null
            : $normalized;
    }

    private function toInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
