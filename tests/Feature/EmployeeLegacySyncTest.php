<?php

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Services\Legacy\EmployeeLegacySyncService;

function employeeLegacySync(): EmployeeLegacySyncService
{
    return app(EmployeeLegacySyncService::class);
}

/**
 * @return list<array<string, mixed>>
 */
function sampleDepartmentRows(): array
{
    return [
        [
            'Id' => 10,
            'DeptCode' => '70482',
            'DeptName' => 'HUMAN RESOURCES',
            'OldDeptCode' => '7048',
        ],
    ];
}

/**
 * @param  list<array<string, mixed>>  $overrides
 * @return list<array<string, mixed>>
 */
function sampleEmployeeRows(array $overrides = []): array
{
    $defaults = [
        [
            'Id' => 101,
            'Group' => 'Staff',
            'EmployeeId' => 'E-101',
            'IdBiometrik' => 'BIO-101',
            'EmployeeName' => 'Alice Legacy',
            'DeptCode' => '70482',
            'CodeEmployee' => 'C-101',
            'Gender' => 'F',
            'PositionName' => 'HR Admin',
            'Contract' => 'Permanent',
        ],
        [
            'Id' => 102,
            'Group' => 'Staff',
            'EmployeeId' => 'E-102',
            'IdBiometrik' => 'BIO-102',
            'EmployeeName' => 'Bob Legacy',
            'DeptCode' => '70482',
            'CodeEmployee' => 'C-102',
            'Gender' => 'M',
            'PositionName' => 'Clerk',
            'Contract' => 'Contract',
        ],
    ];

    if ($overrides === []) {
        return $defaults;
    }

    return $overrides;
}

it('creates departments and employees from injected legacy rows', function () {
    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    expect($result['employees']['created'])->toBe(2)
        ->and($result['departments']['created'])->toBe(1)
        ->and(Employee::query()->count())->toBe(2)
        ->and(EmployeeDepartment::query()->where('code', '70482')->exists())->toBeTrue();

    $alice = Employee::query()->where('employee_id', 'E-101')->first();
    expect($alice)->not->toBeNull()
        ->and($alice->employee_name)->toBe('Alice Legacy')
        ->and(data_get($alice->meta, 'legacy_id'))->toBe(101);
});

it('preserves existing photo_path when refreshing employee data', function () {
    employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $alice = Employee::query()->where('employee_id', 'E-101')->firstOrFail();
    $alice->update([
        'photo_path' => 'assets/images/employee_photos/C-101-alice-legacy-20260101_120000_001.jpg',
    ]);

    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows([
            [
                'Id' => 101,
                'EmployeeId' => 'E-101',
                'EmployeeName' => 'Alice Legacy Updated',
                'DeptCode' => '70482',
                'CodeEmployee' => 'C-101',
                'PositionName' => 'HR Supervisor',
            ],
            [
                'Id' => 102,
                'EmployeeId' => 'E-102',
                'EmployeeName' => 'Bob Legacy',
                'DeptCode' => '70482',
                'CodeEmployee' => 'C-102',
            ],
        ]),
    ]);

    $alice->refresh();

    expect($result['employees']['photos_preserved'])->toBe(1)
        ->and($alice->photo_path)->toBe('assets/images/employee_photos/C-101-alice-legacy-20260101_120000_001.jpg')
        ->and($alice->employee_name)->toBe('Alice Legacy Updated')
        ->and($alice->position_name)->toBe('HR Supervisor');
});

it('leaves unmatched manual employees untouched', function () {
    $department = EmployeeDepartment::query()->create([
        'code' => '70482',
        'name' => 'HUMAN RESOURCES',
    ]);

    Employee::query()->create([
        'employee_department_id' => $department->id,
        'employee_id' => 'MANUAL-ONLY',
        'code_employee' => 'C-MANUAL',
        'employee_name' => 'Manual Only',
        'photo_path' => 'assets/images/employee_photos/manual.jpg',
    ]);

    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $manual = Employee::query()->where('employee_id', 'MANUAL-ONLY')->first();

    expect($manual)->not->toBeNull()
        ->and($manual->trashed())->toBeFalse()
        ->and($manual->employee_name)->toBe('Manual Only')
        ->and($manual->photo_path)->toBe('assets/images/employee_photos/manual.jpg')
        ->and($result['employees']['soft_deleted'])->toBe(0)
        ->and(Employee::query()->count())->toBe(3);
});

it('merges manual employees matched by employee_id and preserves photo', function () {
    $department = EmployeeDepartment::query()->create([
        'code' => '70482',
        'name' => 'HUMAN RESOURCES',
    ]);

    $manual = Employee::query()->create([
        'employee_department_id' => $department->id,
        'employee_id' => 'E-101',
        'code_employee' => 'C-101',
        'employee_name' => 'Alice Manual',
        'photo_path' => 'assets/images/employee_photos/C-101-alice-manual-20260101_120000_001.jpg',
        'position_name' => 'Temp',
    ]);

    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $manual->refresh();

    expect($result['employees']['merged_manual'])->toBe(1)
        ->and($result['employees']['created'])->toBe(1)
        ->and($manual->employee_name)->toBe('Alice Legacy')
        ->and($manual->photo_path)->toBe('assets/images/employee_photos/C-101-alice-manual-20260101_120000_001.jpg')
        ->and(data_get($manual->meta, 'legacy_id'))->toBe(101);
});

it('soft-deletes legacy employees missing from the source', function () {
    employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows([
            [
                'Id' => 101,
                'EmployeeId' => 'E-101',
                'EmployeeName' => 'Alice Legacy',
                'DeptCode' => '70482',
                'CodeEmployee' => 'C-101',
            ],
        ]),
    ]);

    $bob = Employee::withTrashed()->where('employee_id', 'E-102')->first();

    expect($result['employees']['soft_deleted'])->toBe(1)
        ->and($bob)->not->toBeNull()
        ->and($bob->trashed())->toBeTrue()
        ->and(Employee::query()->count())->toBe(1);
});

it('restores soft-deleted employees that reappear in legacy', function () {
    employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows([
            [
                'Id' => 101,
                'EmployeeId' => 'E-101',
                'EmployeeName' => 'Alice Legacy',
                'DeptCode' => '70482',
                'CodeEmployee' => 'C-101',
            ],
        ]),
    ]);

    expect(Employee::withTrashed()->where('employee_id', 'E-102')->first()?->trashed())->toBeTrue();

    $result = employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $bob = Employee::query()->where('employee_id', 'E-102')->first();

    expect($result['employees']['restored'])->toBe(1)
        ->and($bob)->not->toBeNull()
        ->and($bob->trashed())->toBeFalse();
});

it('dry-run does not write to the database', function () {
    $result = employeeLegacySync()->sync([
        'dry_run' => true,
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    expect($result['dry_run'])->toBeTrue()
        ->and($result['employees']['created'])->toBe(2)
        ->and(Employee::query()->count())->toBe(0)
        ->and(EmployeeDepartment::query()->count())->toBe(0);
});

it('skip-soft-delete leaves missing legacy employees active', function () {
    employeeLegacySync()->sync([
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows(),
    ]);

    $result = employeeLegacySync()->sync([
        'skip_soft_delete' => true,
        'department_rows' => sampleDepartmentRows(),
        'employee_rows' => sampleEmployeeRows([
            [
                'Id' => 101,
                'EmployeeId' => 'E-101',
                'EmployeeName' => 'Alice Legacy',
                'DeptCode' => '70482',
                'CodeEmployee' => 'C-101',
            ],
        ]),
    ]);

    expect($result['employees']['soft_deleted'])->toBe(0)
        ->and(Employee::query()->where('employee_id', 'E-102')->exists())->toBeTrue();
});

it('runs via artisan command', function () {
    $exitCode = \Illuminate\Support\Facades\Artisan::call('employees:sync-from-legacy', [
        '--dry-run' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Dry run')
        ->and($output)->toContain('Employees');
});
