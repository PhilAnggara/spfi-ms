<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Support\Concerns\PaginatesLegacySqlServer;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeeController extends Controller
{
    use PaginatesLegacySqlServer;

    /**
     * Display employee list.
     */
    public function index(Request $request)
    {
        $filters = $this->employeeFilters($request);
        $employeesQuery = $this->buildEmployeesQuery($filters);

        $sortDirection = $filters['sort_direction'] === 'asc' ? 'asc' : 'desc';
        $sortDirectionSql = strtoupper($sortDirection);
        $sortBy = $filters['sort_by'];

        $orderableColumns = [
            'employee_name' => 'employees.employee_name',
            'employee_id' => 'employees.employee_id',
            'position_name' => 'employees.position_name',
            'date_hired' => 'employees.date_hired',
            'date_of_birth' => 'employees.date_of_birth',
            'date_terminated' => 'employees.date_terminated',
            'created_at' => 'employees.created_at',
        ];

        $sortColumn = $orderableColumns[$sortBy] ?? $orderableColumns['created_at'];
        $employeesQuery->orderBy($sortColumn, $sortDirection);
        $orderClause = "{$sortColumn} {$sortDirectionSql}, employees.id {$sortDirectionSql}";

        if ($request->query('selection_scope') === 'all_ids') {
            $employeeIds = (clone $employeesQuery)
                ->reorder()
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values();

            return response()->json([
                'ids' => $employeeIds,
                'total' => $employeeIds->count(),
            ]);
        }

        $employeesQuery->orderBy('employees.id', $sortDirection);

        $employees = $this->paginateEloquentForCurrentConnection($employeesQuery, $orderClause, 10);

        $departmentOptions = EmployeeDepartment::query()
            ->select(['id', 'code', 'name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('pages.employees.index', [
            'employees' => $employees,
            'departmentOptions' => $departmentOptions,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{keyword: string, department: string, gender: string, status: string, sort_by: string, sort_direction: string}
     */
    private function employeeFilters(Request $request): array
    {
        $sortBy = mb_strtolower(trim((string) $request->query('sort_by', 'created_at')));
        $sortDirection = mb_strtolower(trim((string) $request->query('sort_direction', 'desc')));

        $allowedSortBy = [
            'employee_name',
            'employee_id',
            'position_name',
            'date_hired',
            'date_of_birth',
            'date_terminated',
            'created_at',
        ];

        $hasPhoto = mb_strtolower(trim((string) $request->query('has_photo', '')));

        return [
            'keyword' => trim((string) $request->query('keyword', '')),
            'department' => trim((string) $request->query('department', '')),
            'gender' => trim((string) $request->query('gender', '')),
            'status' => trim((string) $request->query('status', '')),
            'has_photo' => in_array($hasPhoto, ['yes', 'no'], true) ? $hasPhoto : '',
            'sort_by' => in_array($sortBy, $allowedSortBy, true) ? $sortBy : 'created_at',
            'sort_direction' => in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc',
        ];
    }

    private function buildEmployeesQuery(array $filters)
    {
        $searchTerms = collect(preg_split('/\s+/', mb_strtolower($filters['keyword']), -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->values();

        return Employee::query()
            ->with(['department:id,code,old_code,name'])
            ->when($searchTerms->isNotEmpty(), function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->where(function ($subQuery) use ($term) {
                        $likeTerm = '%' . $term . '%';

                        $subQuery
                            ->whereRaw('LOWER(employee_id) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(code_employee) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(employee_name) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(position_name) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(cell_phone) LIKE ?', [$likeTerm])
                            ->orWhereHas('department', function ($departmentQuery) use ($likeTerm) {
                                $departmentQuery
                                    ->whereRaw('LOWER(code) LIKE ?', [$likeTerm])
                                    ->orWhereRaw('LOWER(old_code) LIKE ?', [$likeTerm])
                                    ->orWhereRaw('LOWER(name) LIKE ?', [$likeTerm]);
                            });
                    });
                }
            })
            ->when($filters['department'] !== '' && is_numeric($filters['department']), function ($query) use ($filters) {
                $query->where('employee_department_id', (int) $filters['department']);
            })
            ->when($filters['gender'] !== '', function ($query) use ($filters) {
                $query->whereRaw('LOWER(gender) = ?', [mb_strtolower($filters['gender'])]);
            })
            ->when($filters['status'] === 'active', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->whereNull('date_terminated')
                        ->orWhereDate('date_terminated', '>', now()->toDateString());
                });
            })
            ->when($filters['status'] === 'terminated', function ($query) {
                $query->whereDate('date_terminated', '<=', now()->toDateString());
            })
            ->when($filters['has_photo'] === 'yes', function ($query) {
                $query->whereNotNull('photo_path')->where('photo_path', '!=', '');
            })
            ->when($filters['has_photo'] === 'no', function ($query) {
                $query->where(function ($sub) {
                    $sub->whereNull('photo_path')->orWhere('photo_path', '');
                });
            });
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $request->session()->flash('employee_create_modal', true);

        $validated = $this->validateEmployee($request);
        $department = EmployeeDepartment::query()->findOrFail((int) $validated['employee_department_id']);
        $codeEmployee = trim((string) $validated['code_employee']);
        $employeeName = trim((string) $validated['employee_name']);
        $photoPath = $this->storeEmployeePhoto($request, $codeEmployee, $employeeName);

        Employee::create($this->employeePayload($validated, $department, $photoPath, $codeEmployee));

        return redirect()->back()->with('success', 'Employee has been created successfully.');
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee)
    {
        $request->session()->flash('employee_edit_id', $employee->id);

        $validated = $this->validateEmployee($request, $employee);
        $department = EmployeeDepartment::query()->findOrFail((int) $validated['employee_department_id']);
        $employeeName = trim((string) ($validated['employee_name'] ?? $employee->employee_name));
        $photoPath = $this->resolveUpdatedPhotoPath($request, $employee, $employeeName);

        // code_employee is immutable after creation; ignore any tampered payload values.
        $employee->update($this->employeePayload($validated, $department, $photoPath, (string) $employee->code_employee));

        return redirect()->back()->with('success', "Employee {$employee->employee_name} has been updated successfully.");
    }

    /**
     * Soft delete the specified employee.
     */
    public function destroy(Employee $employee)
    {
        $employeeName = $employee->employee_name;
        $this->deleteEmployeePhoto($employee->photo_path);
        $employee->delete();

        return redirect()->back()->with('success', "Employee {$employeeName} has been deleted successfully.");
    }

    /**
     * Print one or more employee ID cards.
     */
    public function printIdCards(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'integer', 'exists:employees,id'],
            'valid_until' => ['required', 'date'],
        ]);

        $employeeIds = collect($validated['employee_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        $employeesById = Employee::query()
            ->with(['department:id,code,name'])
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        $employees = $employeeIds
            ->map(static fn (int $id) => $employeesById->get($id))
            ->filter()
            ->values();

        abort_if($employees->isEmpty(), 404, 'Selected employees were not found.');

        $logoPath = public_path('assets/images/sinar.png');

        return Pdf::loadView('pdf.employee-id-cards', [
            'employees' => $employees,
            'validUntil' => Carbon::parse($validated['valid_until']),
            'logoPath' => $logoPath,
        ])->setPaper('a4', 'portrait')->stream('employee-id-cards.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $employeeId = $employee?->id;
        $codeEmployeeRules = [
            'required',
            'string',
            'max:100',
            Rule::unique('employees', 'code_employee')->ignore($employeeId),
        ];

        if ($employee !== null) {
            $codeEmployeeRules = ['sometimes', 'nullable', 'string', 'max:100'];
        }

        return $request->validate([
            'employee_department_id' => ['required', 'exists:employee_departments,id'],
            'employee_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_id')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'code_employee' => $codeEmployeeRules,
            'id_biometrik' => [
                'nullable',
                'string',
                'max:100',
            ],
            'employee_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:M,F'],
            'position_name' => ['nullable', 'string', 'max:255'],
            'pay_type' => ['nullable', 'string', 'max:50'],
            'contract' => ['nullable', 'string', 'max:100'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'date_hired' => ['nullable', 'date'],
            'date_terminated' => ['nullable', 'date', 'after_or_equal:date_hired'],
            'date_of_birth' => ['nullable', 'date'],
            'cell_phone' => ['nullable', 'string', 'max:50'],
            'account_no' => ['nullable', 'string', 'max:255'],
            'identity_card_no' => ['nullable', 'string', 'max:255'],
            'insurance_no' => ['nullable', 'string', 'max:255'],
            'no_astek' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:100'],
            'education' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function employeePayload(
        array $validated,
        EmployeeDepartment $department,
        ?string $photoPath = null,
        ?string $codeEmployee = null
    ): array
    {
        $resolvedCodeEmployee = $this->nullableText($codeEmployee);

        if ($resolvedCodeEmployee === null) {
            $resolvedCodeEmployee = $this->nullableText($validated['code_employee'] ?? null);
        }

        return [
            'employee_department_id' => (int) $department->id,
            'employee_id' => trim((string) $validated['employee_id']),
            'code_employee' => $resolvedCodeEmployee,
            'id_biometrik' => $this->nullableText($validated['id_biometrik'] ?? null),
            'employee_name' => trim((string) $validated['employee_name']),
            'photo_path' => $photoPath,
            'gender' => $this->nullableText($validated['gender'] ?? null),
            'position_name' => $this->nullableText($validated['position_name'] ?? null),
            'pay_type' => $this->nullableText($validated['pay_type'] ?? null),
            'contract' => $this->nullableText($validated['contract'] ?? null),
            'civil_status' => $this->nullableText($validated['civil_status'] ?? null),
            'date_hired' => $validated['date_hired'] ?? null,
            'date_terminated' => $validated['date_terminated'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'cell_phone' => $this->nullableText($validated['cell_phone'] ?? null),
            'account_no' => $this->nullableText($validated['account_no'] ?? null),
            'identity_card_no' => $this->nullableText($validated['identity_card_no'] ?? null),
            'insurance_no' => $this->nullableText($validated['insurance_no'] ?? null),
            'no_astek' => $this->nullableText($validated['no_astek'] ?? null),
            'religion' => $this->nullableText($validated['religion'] ?? null),
            'education' => $this->nullableText($validated['education'] ?? null),
            'remarks' => $this->nullableText($validated['remarks'] ?? null),
            'legacy_department_code' => $department->code,
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function storeEmployeePhoto(
        Request $request,
        string $codeEmployee,
        string $employeeName,
        ?string $oldPhotoPath = null
    ): ?string
    {
        if (! $request->hasFile('photo')) {
            return $oldPhotoPath;
        }

        $directory = public_path('assets/images/employee_photos');
        File::ensureDirectoryExists($directory);

        $file = $request->file('photo');
        $sanitizedCodeEmployee = $this->sanitizeCodeEmployeeForFilename($codeEmployee);
        $employeeNameSlug = Str::slug($employeeName, '-') ?: 'employee';
        $filename = sprintf(
            '%s-%s-%s.%s',
            $sanitizedCodeEmployee,
            $employeeNameSlug,
            now()->format('Ymd_His_v'),
            $file->getClientOriginalExtension()
        );

        $file->move($directory, $filename);

        $this->deleteEmployeePhoto($oldPhotoPath);

        return 'assets/images/employee_photos/' . $filename;
    }

    private function resolveUpdatedPhotoPath(Request $request, Employee $employee, string $employeeName): ?string
    {
        if ($request->hasFile('photo')) {
            return $this->storeEmployeePhoto(
                $request,
                (string) $employee->code_employee,
                $employeeName,
                $employee->photo_path
            );
        }

        if ($request->boolean('remove_photo')) {
            $this->deleteEmployeePhoto($employee->photo_path);

            return null;
        }

        return $employee->photo_path;
    }

    private function sanitizeCodeEmployeeForFilename(string $codeEmployee): string
    {
        $normalized = trim($codeEmployee);
        $normalized = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, " .-_");

        return $normalized === '' ? 'employee-code' : $normalized;
    }

    private function deleteEmployeePhoto(?string $photoPath): void
    {
        if (! $photoPath) {
            return;
        }

        $absolutePath = public_path($photoPath);

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }
}
