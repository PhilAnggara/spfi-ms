<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
        $codeEmployeeOwnerLookup = $this->buildCodeEmployeeOwnerLookup();
        $inserted = 0;
        $skipped = 0;
        $duplicateAdjusted = 0;
        $isSqlServer = DB::connection()->getDriverName() === 'sqlsrv';

        if ($isSqlServer) {
            DB::unprepared('SET IDENTITY_INSERT [employees] ON');
        }

        try {
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
                $ownerKey = $this->ownerKeyFor($employeeId);
                $rawCodeEmployee = $this->normalizeText($row['CodeEmployee'] ?? $row['code_employee'] ?? null)
                    ?? "EMP-{$employeeId}";
                $codeEmployee = $this->resolveUniqueCodeEmployee($rawCodeEmployee, $ownerKey, $codeEmployeeOwnerLookup, $duplicateAdjusted);

                $payload = [
                    'employee_department_id' => $departmentId,
                    'employee_group' => $this->normalizeText($row['Group'] ?? $row['employee_group'] ?? null),
                    'employee_id' => $employeeId,
                    'code_employee' => $codeEmployee,
                    'id_biometrik' => $this->normalizeText($row['IdBiometrik'] ?? $row['id_biometrik'] ?? null),
                    'account_no' => $this->normalizeText($row['AccountNo'] ?? $row['account_no'] ?? null),
                    'employee_name' => $employeeName,
                    'photo_path' => null,
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
        } finally {
            if ($isSqlServer) {
                DB::unprepared('SET IDENTITY_INSERT [employees] OFF');
            }
        }

        $relinkStats = $this->relinkPhotoPaths();

        $this->command?->info("✓ [employee] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
        $this->command?->info("✓ [employee] Duplicate code_employee adjusted: {$duplicateAdjusted}");
        $this->command?->info("✓ [employee] Photo relinked: {$relinkStats['relinked']}, Missing photo candidates: {$relinkStats['missing']}");
        $this->command?->info(
            "✓ [employee] Photo match source => new-pattern: {$relinkStats['matched_new']}, legacy-pattern: {$relinkStats['matched_legacy']}"
        );
        $this->command?->info(
            "✓ [employee] Photo relink source => new-pattern: {$relinkStats['relinked_new']}, legacy-pattern: {$relinkStats['relinked_legacy']}"
        );
        $this->command?->info(
            "✓ [employee] Photo already-linked => new-pattern: {$relinkStats['already_linked_new']}, legacy-pattern: {$relinkStats['already_linked_legacy']}"
        );
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

    /**
     * @return array<string, string>
     */
    private function buildCodeEmployeeOwnerLookup(): array
    {
        $lookup = [];

        $rows = DB::table('employees')
            ->select(['employee_id', 'code_employee'])
            ->whereNotNull('employee_id')
            ->whereNotNull('code_employee')
            ->get();

        foreach ($rows as $row) {
            $normalizedCode = $this->normalizeText($row->code_employee);
            $employeeId = $this->normalizeText($row->employee_id);

            if ($normalizedCode === null || $employeeId === null) {
                continue;
            }

            $lookup[$this->normalizeLookupKey($normalizedCode) ?? ''] = $this->ownerKeyFor($employeeId);
        }

        return array_filter($lookup, static fn (string $owner) => $owner !== '');
    }

    private function ownerKeyFor(string $employeeId): string
    {
        return 'emp:' . strtolower(trim($employeeId));
    }

    /**
     * @param  array<string, string>  $ownerLookup
     */
    private function resolveUniqueCodeEmployee(
        string $rawCodeEmployee,
        string $ownerKey,
        array &$ownerLookup,
        int &$duplicateAdjusted
    ): string {
        $baseCode = trim($rawCodeEmployee);
        if ($baseCode === '') {
            $baseCode = 'EMP';
        }

        $candidate = $baseCode;
        $suffixCounter = 2;

        while (true) {
            $normalizedCandidate = $this->normalizeLookupKey($candidate);

            if ($normalizedCandidate === null) {
                $candidate = 'EMP';
                $normalizedCandidate = 'emp';
            }

            if (! isset($ownerLookup[$normalizedCandidate]) || $ownerLookup[$normalizedCandidate] === $ownerKey) {
                $ownerLookup[$normalizedCandidate] = $ownerKey;
                return $candidate;
            }

            $suffix = '-dup-' . $suffixCounter;
            $baseLimit = max(1, 100 - strlen($suffix));
            $candidate = rtrim(substr($baseCode, 0, $baseLimit)) . $suffix;
            $suffixCounter++;
            $duplicateAdjusted++;
        }
    }

    /**
     * @return array{relinked:int,missing:int,matched_new:int,matched_legacy:int,relinked_new:int,relinked_legacy:int,already_linked_new:int,already_linked_legacy:int}
     */
    private function relinkPhotoPaths(): array
    {
        $directory = public_path('assets/images/employee_photos');
        if (! File::isDirectory($directory)) {
            return [
                'relinked' => 0,
                'missing' => 0,
                'matched_new' => 0,
                'matched_legacy' => 0,
                'relinked_new' => 0,
                'relinked_legacy' => 0,
                'already_linked_new' => 0,
                'already_linked_legacy' => 0,
            ];
        }

        $photoFiles = [];
        foreach (File::files($directory) as $file) {
            $photoFiles[] = [
                'name' => $file->getFilename(),
                'mtime' => $file->getMTime(),
            ];
        }

        if ($photoFiles === []) {
            return [
                'relinked' => 0,
                'missing' => 0,
                'matched_new' => 0,
                'matched_legacy' => 0,
                'relinked_new' => 0,
                'relinked_legacy' => 0,
                'already_linked_new' => 0,
                'already_linked_legacy' => 0,
            ];
        }

        $employees = DB::table('employees')
            ->select(['id', 'employee_id', 'code_employee', 'photo_path'])
            ->whereNotNull('employee_id')
            ->whereNotNull('code_employee')
            ->get();

        $relinked = 0;
        $missing = 0;
        $matchedNew = 0;
        $matchedLegacy = 0;
        $relinkedNew = 0;
        $relinkedLegacy = 0;
        $alreadyLinkedNew = 0;
        $alreadyLinkedLegacy = 0;

        foreach ($employees as $employee) {
            $codeSlug = Str::slug((string) $employee->code_employee, '-');
            $employeeSlug = Str::slug((string) $employee->employee_id, '-');

            $candidate = $this->findLatestPhotoByPatterns($photoFiles, $codeSlug, $employeeSlug);
            if ($candidate === null) {
                $missing++;
                continue;
            }

            if ($candidate['source'] === 'new') {
                $matchedNew++;
            } else {
                $matchedLegacy++;
            }

            $path = 'assets/images/employee_photos/' . $candidate['name'];
            if ((string) $employee->photo_path === $path) {
                if ($candidate['source'] === 'new') {
                    $alreadyLinkedNew++;
                } else {
                    $alreadyLinkedLegacy++;
                }

                continue;
            }

            DB::table('employees')
                ->where('id', $employee->id)
                ->update([
                    'photo_path' => $path,
                    'updated_at' => now(),
                ]);

            $relinked++;
            if ($candidate['source'] === 'new') {
                $relinkedNew++;
            } else {
                $relinkedLegacy++;
            }
        }

        return [
            'relinked' => $relinked,
            'missing' => $missing,
            'matched_new' => $matchedNew,
            'matched_legacy' => $matchedLegacy,
            'relinked_new' => $relinkedNew,
            'relinked_legacy' => $relinkedLegacy,
            'already_linked_new' => $alreadyLinkedNew,
            'already_linked_legacy' => $alreadyLinkedLegacy,
        ];
    }

    /**
     * @param  array<int, array{name:string,mtime:int}>  $photoFiles
     * @return array{name:string,source:'new'|'legacy'}|null
     */
    private function findLatestPhotoByPatterns(array $photoFiles, string $codeSlug, string $employeeSlug): ?array
    {
        $newPattern = null;
        if ($codeSlug !== '') {
            $newPattern = '/^' . preg_quote($codeSlug, '/') . '-\\d{14}-[a-z0-9]{6}\\.[a-z0-9]+$/i';
        }

        $legacyPattern = null;
        if ($employeeSlug !== '') {
            $legacyPattern = '/^' . preg_quote($employeeSlug, '/') . '-\\d{14}-[a-z0-9]{6}\\.[a-z0-9]+$/i';
        }

        $matches = [];

        if ($newPattern !== null) {
            foreach ($photoFiles as $photoFile) {
                if (preg_match($newPattern, $photoFile['name']) === 1) {
                    $matches[] = $photoFile;
                }
            }
        }

        if ($matches === [] && $legacyPattern !== null) {
            foreach ($photoFiles as $photoFile) {
                if (preg_match($legacyPattern, $photoFile['name']) === 1) {
                    $matches[] = $photoFile;
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return [
            'name' => $matches[0]['name'],
            'source' => $newPattern !== null && preg_match($newPattern, $matches[0]['name']) === 1 ? 'new' : 'legacy',
        ];
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
