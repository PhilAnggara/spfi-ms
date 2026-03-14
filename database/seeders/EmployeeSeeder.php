<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = $this->loadRows('employee');

        if (empty($rows)) {
            $this->command?->warn('No employee rows found from configured source.');
            return;
        }

        $departmentLookup = $this->buildDepartmentLookup();
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $legacyId = $this->toInteger($row['Id'] ?? $row['id'] ?? null);
            $employeeId = $this->normalizeText($row['EmployeeId'] ?? $row['employee_id'] ?? null);
            $employeeName = $this->normalizeText($row['EmployeeName'] ?? $row['employee_name'] ?? null);

            if ($employeeId === null || $employeeName === null) {
                $skipped++;
                continue;
            }

            $legacyDepartmentCode = $this->normalizeText($row['DeptCode'] ?? $row['dept_code'] ?? null);
            $departmentId = $this->resolveDepartmentId($legacyDepartmentCode, $departmentLookup);

            $payload = [
                'employee_department_id' => $departmentId,
                'employee_group' => $this->normalizeText($row['Group'] ?? $row['employee_group'] ?? null),
                'employee_id' => $employeeId,
                'code_employee' => $this->normalizeText($row['CodeEmployee'] ?? $row['code_employee'] ?? null),
                'id_biometrik' => $this->normalizeText($row['IdBiometrik'] ?? $row['id_biometrik'] ?? null),
                'account_no' => $this->normalizeText($row['AccountNo'] ?? $row['account_no'] ?? null),
                'employee_name' => $employeeName,
                'date_of_birth' => $this->parseDate($row['DateOfBirtH'] ?? $row['date_of_birth'] ?? null),
                'gender' => $this->normalizeText($row['Gender'] ?? $row['gender'] ?? null),
                'legacy_department_code' => $legacyDepartmentCode,
                'job_code' => $this->normalizeText($row['JobCode'] ?? $row['job_code'] ?? null),
                'position' => $this->normalizeText($row['Position'] ?? $row['position'] ?? null),
                'position_name' => $this->normalizeText($row['PositionName'] ?? $row['position_name'] ?? null),
                'pay_type' => $this->normalizeText($row['PayType'] ?? $row['pay_type'] ?? null),
                'date_hired' => $this->parseDate($row['DateHired'] ?? $row['date_hired'] ?? null),
                'civil_status' => $this->normalizeText($row['CStatus'] ?? $row['civil_status'] ?? null),
                'cell_phone' => $this->normalizeText($row['CellPhone'] ?? $row['cell_phone'] ?? null),
                'identity_card_no' => $this->normalizeText($row['IdentityCardNo'] ?? $row['identity_card_no'] ?? null),
                'insurance_no' => $this->normalizeText($row['InsuranceNo'] ?? $row['insurance_no'] ?? null),
                'mothers_name' => $this->normalizeText($row['MothersName'] ?? $row['mothers_name'] ?? null),
                'passport' => $this->normalizeText($row['Passport'] ?? $row['passport'] ?? null),
                'basic_rate' => $this->toDecimal($row['BasicRate'] ?? $row['basic_rate'] ?? null),
                'old_rate' => $this->toDecimal($row['OldRate'] ?? $row['old_rate'] ?? null),
                'effective_date' => $this->parseDate($row['EffectiveDate'] ?? $row['effective_date'] ?? null),
                'tax_no' => $this->normalizeText($row['TaxNo'] ?? $row['tax_no'] ?? null),
                'chrono_no' => $this->normalizeText($row['ChronoNo'] ?? $row['chrono_no'] ?? null),
                'rest_day' => $this->normalizeText($row['RestDay'] ?? $row['rest_day'] ?? null),
                'half_day' => $this->normalizeText($row['HalfDay'] ?? $row['half_day'] ?? null),
                'shift_code' => $this->normalizeText($row['ShiftCode'] ?? $row['shift_code'] ?? null),
                'hours_per_day' => $this->toDecimal($row['HoursPerDay'] ?? $row['hours_per_day'] ?? null),
                'date_terminated' => $this->parseDate($row['DateTerminated'] ?? $row['date_terminated'] ?? null),
                'emp_shift' => $this->normalizeText($row['EmpShift'] ?? $row['emp_shift'] ?? null),
                'max_sl' => $this->toDecimal($row['MaxSL'] ?? $row['max_sl'] ?? null),
                'max_vl' => $this->toDecimal($row['MaxVL'] ?? $row['max_vl'] ?? null),
                'new_sl' => $this->toDecimal($row['NewSL'] ?? $row['new_sl'] ?? null),
                'new_vl' => $this->toDecimal($row['NewVL'] ?? $row['new_vl'] ?? null),
                'meals' => $this->toDecimal($row['Meals'] ?? $row['meals'] ?? null),
                'transpo' => $this->toDecimal($row['Transpo'] ?? $row['transpo'] ?? null),
                'bonus' => $this->toDecimal($row['Bonus'] ?? $row['bonus'] ?? null),
                'religion' => $this->normalizeText($row['Religion'] ?? $row['religion'] ?? null),
                'education' => $this->normalizeText($row['Education'] ?? $row['education'] ?? null),
                'hk' => $this->normalizeText($row['HK'] ?? $row['hk'] ?? null),
                'level' => $this->normalizeText($row['Level'] ?? $row['level'] ?? null),
                'remarks' => $this->normalizeText($row['Remarks'] ?? $row['remarks'] ?? null),
                'no_astek' => $this->normalizeText($row['NoAstek'] ?? $row['no_astek'] ?? null),
                'contract' => $this->normalizeText($row['Contract'] ?? $row['contract'] ?? null),
                'meta' => json_encode([
                    'legacy_id' => $legacyId,
                    'legacy_department_code' => $legacyDepartmentCode,
                ]),
                'updated_at' => now(),
                'deleted_at' => null,
            ];

            if ($legacyId !== null) {
                DB::table('employees')->updateOrInsert(
                    ['id' => $legacyId],
                    ['created_at' => now()] + $payload
                );
            } else {
                DB::table('employees')->updateOrInsert(
                    ['employee_id' => $employeeId],
                    ['created_at' => now()] + $payload
                );
            }

            $inserted++;
        }

        $this->command?->info("✓ [employee] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
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

    /**
     * @return array<string, int>
     */
    private function buildDepartmentLookup(): array
    {
        $lookup = [];

        $rows = DB::table('employee_departments')
            ->select(['id', 'code', 'old_code'])
            ->get();

        foreach ($rows as $row) {
            foreach ([$row->code, $row->old_code] as $code) {
                $normalized = $this->normalizeLookupKey($code);
                if ($normalized === null || isset($lookup[$normalized])) {
                    continue;
                }

                $lookup[$normalized] = (int) $row->id;
            }
        }

        return $lookup;
    }

    private function resolveDepartmentId(?string $code, array $lookup): ?int
    {
        $normalized = $this->normalizeLookupKey($code);
        if ($normalized === null) {
            return null;
        }

        if (isset($lookup[$normalized])) {
            return $lookup[$normalized];
        }

        $keys = array_keys($lookup);
        usort($keys, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($keys as $key) {
            if (str_starts_with($normalized, $key)) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private function normalizeLookupKey(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);

        return $normalized === null ? null : strtolower($normalized);
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

    private function toDecimal(mixed $value): float
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return 0;
        }

        $normalized = str_replace(',', '', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : 0;
    }

    private function parseDate(mixed $value): ?string
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return Carbon::parse($normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
