<?php

namespace App\Services\Legacy;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class EmployeeLegacySyncService
{
    use ResolvesLegacyImport;

    /**
     * @param  array{
     *     dry_run?: bool,
     *     skip_soft_delete?: bool,
     *     department_rows?: list<array<string, mixed>>|null,
     *     employee_rows?: list<array<string, mixed>>|null,
     *     on_warning?: callable(string): void|null
     * }  $options
     * @return array{
     *     source: string,
     *     departments: array{created: int, updated: int, skipped: int},
     *     employees: array{
     *         created: int,
     *         updated: int,
     *         merged_manual: int,
     *         soft_deleted: int,
     *         restored: int,
     *         skipped: int,
     *         code_adjusted: int,
     *         photos_preserved: int,
     *         photos_relinked: int
     *     },
     *     dry_run: bool
     * }
     */
    public function sync(array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $skipSoftDelete = (bool) ($options['skip_soft_delete'] ?? false);
        $onWarning = $options['on_warning'] ?? null;

        $departmentRows = $options['department_rows'] ?? $this->loadRows('employee_department', $onWarning);
        $employeeRows = $options['employee_rows'] ?? $this->loadRows('employee', $onWarning);

        $source = array_key_exists('department_rows', $options) || array_key_exists('employee_rows', $options)
            ? 'injected'
            : ($this->isLegacySource() ? 'legacy' : 'csv');

        $departmentStats = $this->syncDepartments($departmentRows, $dryRun);
        $departmentLookup = $this->buildDepartmentLookup();
        $employeeStats = $this->syncEmployees(
            $employeeRows,
            $departmentLookup,
            $dryRun,
            $skipSoftDelete,
        );

        return [
            'source' => $source,
            'departments' => $departmentStats,
            'employees' => $employeeStats,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    private function syncDepartments(array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $byCode = EmployeeDepartment::query()
            ->withTrashed()
            ->get()
            ->keyBy(fn (EmployeeDepartment $department) => $this->normalizeLookupKey($department->code) ?? '');

        foreach ($rows as $row) {
            $code = $this->normalizeText($row['DeptCode'] ?? $row['dept_code'] ?? $row['code'] ?? null);
            $name = $this->normalizeText($row['DeptName'] ?? $row['dept_name'] ?? $row['name'] ?? null);

            if ($code === null || $name === null) {
                $skipped++;

                continue;
            }

            $legacyId = $this->toInteger($row['Id'] ?? $row['id'] ?? null);
            $lookupKey = $this->normalizeLookupKey($code) ?? '';
            $existing = $byCode->get($lookupKey);

            $payload = [
                'code' => $code,
                'old_code' => $this->normalizeText($row['OldDeptCode'] ?? $row['old_dept_code'] ?? $row['old_code'] ?? null),
                'name' => $name,
                'is_active' => true,
                'meta' => array_filter([
                    'legacy_id' => $legacyId,
                    'legacy_row' => $row,
                ], static fn ($value) => $value !== null),
                'deleted_at' => null,
            ];

            if ($existing === null) {
                $created++;

                if (! $dryRun) {
                    $department = EmployeeDepartment::query()->create($payload);
                    $byCode->put($lookupKey, $department);
                }

                continue;
            }

            $updated++;

            if (! $dryRun) {
                $existing->fill($payload);
                $existing->save();
                $byCode->put($lookupKey, $existing);
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $departmentLookup
     * @return array{
     *     created: int,
     *     updated: int,
     *     merged_manual: int,
     *     soft_deleted: int,
     *     restored: int,
     *     skipped: int,
     *     code_adjusted: int,
     *     photos_preserved: int,
     *     photos_relinked: int
     * }
     */
    private function syncEmployees(
        array $rows,
        array $departmentLookup,
        bool $dryRun,
        bool $skipSoftDelete,
    ): array {
        $created = 0;
        $updated = 0;
        $mergedManual = 0;
        $softDeleted = 0;
        $restored = 0;
        $skipped = 0;
        $codeAdjusted = 0;
        $photosPreserved = 0;
        $photosRelinked = 0;

        $employees = Employee::withTrashed()->get();
        $byLegacyId = [];
        $byEmployeeId = [];

        foreach ($employees as $employee) {
            $this->indexEmployee($employee, $byLegacyId, $byEmployeeId);
        }

        $codeEmployeeOwnerLookup = $this->buildCodeEmployeeOwnerLookup($employees);
        $seenLegacyIds = [];

        foreach ($rows as $row) {
            $legacyId = $this->toInteger($row['Id'] ?? $row['id'] ?? null);
            $employeeId = $this->normalizeText($row['EmployeeId'] ?? $row['employee_id'] ?? null);
            $employeeName = $this->normalizeText($row['EmployeeName'] ?? $row['employee_name'] ?? null);

            if ($employeeId === null || $employeeName === null) {
                $skipped++;

                continue;
            }

            if ($legacyId !== null) {
                $seenLegacyIds[$legacyId] = true;
            }

            $legacyDepartmentCode = $this->normalizeText($row['DeptCode'] ?? $row['dept_code'] ?? null);
            $departmentId = $this->resolveDepartmentId($legacyDepartmentCode, $departmentLookup);
            $ownerKey = $this->ownerKeyFor($employeeId);
            $rawCodeEmployee = $this->normalizeText($row['CodeEmployee'] ?? $row['code_employee'] ?? null)
                ?? "EMP-{$employeeId}";

            [$match, $matchType] = $this->findMatch(
                $legacyId,
                $employeeId,
                $byLegacyId,
                $byEmployeeId,
            );

            $codeEmployee = $this->resolveUniqueCodeEmployee(
                $rawCodeEmployee,
                $ownerKey,
                $codeEmployeeOwnerLookup,
            );

            if ($codeEmployee !== $rawCodeEmployee) {
                $codeAdjusted++;
            }

            $payload = $this->buildEmployeePayload(
                $row,
                $departmentId,
                $employeeId,
                $codeEmployee,
                $employeeName,
                $legacyId,
                $legacyDepartmentCode,
            );

            if ($match === null) {
                $created++;

                if (! $dryRun) {
                    $payload['photo_path'] = null;
                    $employee = Employee::query()->create($payload);
                    $this->indexEmployee($employee, $byLegacyId, $byEmployeeId);
                }

                continue;
            }

            $wasTrashed = $match->trashed();
            $hadNoLegacyId = blank(data_get($match->meta, 'legacy_id'));
            $hadPhoto = filled($match->photo_path);

            if ($hadPhoto) {
                $photosPreserved++;
                $payload['photo_path'] = $match->photo_path;
            } else {
                $payload['photo_path'] = null;
            }

            if ($matchType === 'employee_id' && $hadNoLegacyId) {
                $mergedManual++;
            } else {
                $updated++;
            }

            if ($wasTrashed) {
                $restored++;
            }

            if (! $dryRun) {
                $match->fill($payload);
                $match->deleted_at = null;
                $match->save();
                $this->indexEmployee($match, $byLegacyId, $byEmployeeId);
            }
        }

        if (! $skipSoftDelete) {
            foreach ($employees as $employee) {
                $legacyId = $this->toInteger(data_get($employee->meta, 'legacy_id'));

                if ($legacyId === null) {
                    continue;
                }

                if (isset($seenLegacyIds[$legacyId])) {
                    continue;
                }

                if ($employee->trashed()) {
                    continue;
                }

                $softDeleted++;

                if (! $dryRun) {
                    $employee->delete();
                }
            }
        }

        if (! $dryRun) {
            $photosRelinked = $this->relinkMissingPhotoPaths();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'merged_manual' => $mergedManual,
            'soft_deleted' => $softDeleted,
            'restored' => $restored,
            'skipped' => $skipped,
            'code_adjusted' => $codeAdjusted,
            'photos_preserved' => $photosPreserved,
            'photos_relinked' => $photosRelinked,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildEmployeePayload(
        array $row,
        ?int $departmentId,
        string $employeeId,
        string $codeEmployee,
        string $employeeName,
        ?int $legacyId,
        ?string $legacyDepartmentCode,
    ): array {
        $meta = [
            'legacy_id' => $legacyId,
            'legacy_department_code' => $legacyDepartmentCode,
        ];

        return [
            'employee_department_id' => $departmentId,
            'employee_group' => $this->normalizeText($row['Group'] ?? $row['employee_group'] ?? null),
            'employee_id' => $employeeId,
            'code_employee' => $codeEmployee,
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
            'meta' => $meta,
        ];
    }

    /**
     * Match only by legacy id / employee_id.
     * Never match by code_employee alone — legacy reuses codes across people.
     *
     * @param  array<int, Employee>  $byLegacyId
     * @param  array<string, Employee>  $byEmployeeId
     * @return array{0: Employee|null, 1: string|null}
     */
    private function findMatch(
        ?int $legacyId,
        string $employeeId,
        array $byLegacyId,
        array $byEmployeeId,
    ): array {
        if ($legacyId !== null && isset($byLegacyId[$legacyId])) {
            return [$byLegacyId[$legacyId], 'legacy_id'];
        }

        $employeeIdKey = $this->normalizeLookupKey($employeeId);
        if ($employeeIdKey !== null && isset($byEmployeeId[$employeeIdKey])) {
            return [$byEmployeeId[$employeeIdKey], 'employee_id'];
        }

        return [null, null];
    }

    /**
     * @param  array<int, Employee>  $byLegacyId
     * @param  array<string, Employee>  $byEmployeeId
     */
    private function indexEmployee(
        Employee $employee,
        array &$byLegacyId,
        array &$byEmployeeId,
    ): void {
        $legacyId = $this->toInteger(data_get($employee->meta, 'legacy_id'));
        if ($legacyId !== null) {
            $byLegacyId[$legacyId] = $employee;
        }

        $employeeIdKey = $this->normalizeLookupKey($employee->employee_id);
        if ($employeeIdKey !== null) {
            $byEmployeeId[$employeeIdKey] = $employee;
        }
    }

    /**
     * @return array<string, int>
     */
    private function buildDepartmentLookup(): array
    {
        $lookup = [];

        $rows = EmployeeDepartment::query()
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
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return array<string, string>
     */
    private function buildCodeEmployeeOwnerLookup($employees): array
    {
        $lookup = [];

        foreach ($employees as $row) {
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
        return 'emp:'.strtolower(trim($employeeId));
    }

    /**
     * @param  array<string, string>  $ownerLookup
     */
    private function resolveUniqueCodeEmployee(
        string $rawCodeEmployee,
        string $ownerKey,
        array &$ownerLookup,
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

            $suffix = '-dup-'.$suffixCounter;
            $baseLimit = max(1, 100 - strlen($suffix));
            $candidate = rtrim(substr($baseCode, 0, $baseLimit)).$suffix;
            $suffixCounter++;
        }
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

    /**
     * @param  callable(string): void|null  $onWarning
     * @return list<array<string, mixed>>
     */
    private function loadRows(string $dataset, mixed $onWarning = null): array
    {
        $legacyRows = $this->resolveRows(
            $dataset,
            is_callable($onWarning) ? $onWarning : null,
        );

        if ($this->isLegacySource() && $legacyRows !== []) {
            return $legacyRows;
        }

        return $this->readCsvRows($dataset, $onWarning);
    }

    /**
     * @param  callable(string): void|null  $onWarning
     * @return list<array<string, mixed>>
     */
    private function readCsvRows(string $dataset, mixed $onWarning = null): array
    {
        try {
            $csvPath = $this->csvPathFor($dataset);
        } catch (Throwable $e) {
            if (is_callable($onWarning)) {
                $onWarning($e->getMessage());
            }

            return [];
        }

        if (! file_exists($csvPath)) {
            if (is_callable($onWarning)) {
                $onWarning("CSV for dataset [{$dataset}] not found at {$csvPath}");
            }

            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            if (is_callable($onWarning)) {
                $onWarning("Unable to open CSV for dataset [{$dataset}] at {$csvPath}");
            }

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

    private function relinkMissingPhotoPaths(): int
    {
        $directory = public_path('assets/images/employee_photos');
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $photoFiles = [];
        foreach (File::files($directory) as $file) {
            $photoFiles[] = [
                'name' => $file->getFilename(),
                'mtime' => $file->getMTime(),
            ];
        }

        if ($photoFiles === []) {
            return 0;
        }

        $employees = Employee::query()
            ->select(['id', 'code_employee', 'employee_name', 'photo_path'])
            ->whereNotNull('code_employee')
            ->where(function ($query): void {
                $query->whereNull('photo_path')->orWhere('photo_path', '');
            })
            ->get();

        $relinked = 0;

        foreach ($employees as $employee) {
            $codeToken = $this->sanitizeCodeEmployeeForFilename((string) $employee->code_employee);
            $employeeNameSlug = Str::slug((string) $employee->employee_name, '-') ?: 'employee';
            $candidate = $this->findLatestPhotoByNewPattern($photoFiles, $codeToken, $employeeNameSlug);

            if ($candidate === null) {
                continue;
            }

            $path = 'assets/images/employee_photos/'.$candidate['name'];
            $employee->photo_path = $path;
            $employee->save();
            $relinked++;
        }

        return $relinked;
    }

    /**
     * @param  array<int, array{name: string, mtime: int}>  $photoFiles
     * @return array{name: string, mtime: int}|null
     */
    private function findLatestPhotoByNewPattern(
        array $photoFiles,
        string $codeToken,
        string $employeeNameSlug,
    ): ?array {
        if ($codeToken === '' || $employeeNameSlug === '') {
            return null;
        }

        $pattern = '/^'
            .preg_quote($codeToken, '/')
            .'-'
            .preg_quote($employeeNameSlug, '/')
            .'-\d{8}_\d{6}_\d{3}(?:-\d+)?\.[a-z0-9]+$/i';

        $matches = [];
        foreach ($photoFiles as $photoFile) {
            if (preg_match($pattern, $photoFile['name']) === 1) {
                $matches[] = $photoFile;
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);

        return $matches[0];
    }

    private function sanitizeCodeEmployeeForFilename(string $codeEmployee): string
    {
        $normalized = trim($codeEmployee);
        $normalized = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, ' .-_');

        return $normalized === '' ? 'employee-code' : $normalized;
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
        } catch (Throwable) {
            return null;
        }
    }
}
