<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function tearDown(): void
    {
        foreach (File::glob(public_path('assets/images/employee_photos/test-emp-*')) ?: [] as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $department = Department::query()->create([
            'name' => 'Information Technology',
            'code' => '7056',
            'alias' => 'IT',
        ]);

        $this->admin = User::query()->create([
            'name' => 'Administrator Employee Test',
            'username' => 'admin-employee',
            'email' => 'admin-employee@example.test',
            'password' => Hash::make('password'),
            'department_id' => $department->id,
            'role' => 'Manager',
        ]);

        $this->admin->assignRole('administrator');
    }

    public function test_employee_index_renders_filter_results(): void
    {
        $department = EmployeeDepartment::create([
            'code' => '70482',
            'old_code' => '7048',
            'name' => 'HUMAN RESOURCES TEST',
        ]);

        Employee::create([
            'employee_department_id' => $department->id,
            'employee_id' => 'EMP-001',
            'code_employee' => 'C-001',
            'employee_name' => 'Alice Example',
            'gender' => 'F',
            'position_name' => 'HR Admin',
        ]);

        $this->actingAs($this->admin)
            ->get(route('employees.index', ['keyword' => 'alice']))
            ->assertOk()
            ->assertSee('Employee Master List')
            ->assertSee('Alice Example');
    }

    public function test_employee_can_be_stored_updated_and_soft_deleted(): void
    {
        $department = EmployeeDepartment::create([
            'code' => '70331',
            'old_code' => '7033',
            'name' => 'INSIDE-SANITATION',
        ]);

        $storePayload = [
            'employee_department_id' => $department->id,
            'employee_id' => 'TEST-EMP-002',
            'code_employee' => 'C-002',
            'id_biometrik' => 'BIO-002',
            'employee_name' => 'Budi Example',
            'gender' => 'M',
            'position_name' => 'Supervisor',
            'pay_type' => 'D',
            'contract' => 'PKWT',
            'civil_status' => 'M/1',
            'date_hired' => '2025-01-10',
            'date_of_birth' => '1997-05-11',
            'cell_phone' => '081234567890',
            'account_no' => 'ACC-001',
            'identity_card_no' => 'KTP-001',
            'insurance_no' => 'INS-001',
            'no_astek' => 'AST-001',
            'religion' => 'ISLAM',
            'education' => 'S1',
            'remarks' => 'Imported from employee test',
            'photo' => UploadedFile::fake()->image('employee.jpg', 400, 500),
        ];

        $this->actingAs($this->admin)
            ->post(route('employees.store'), $storePayload)
            ->assertRedirect();

        $employee = Employee::query()->where('employee_id', 'TEST-EMP-002')->firstOrFail();

        $this->assertSame('Budi Example', $employee->employee_name);
        $this->assertSame('70331', $employee->legacy_department_code);
        $this->assertNotNull($employee->photo_path);
        $this->assertTrue(File::exists(public_path($employee->photo_path)));

        $this->actingAs($this->admin)
            ->put(route('employees.update', $employee), [
                'employee_department_id' => $department->id,
            'employee_id' => 'TEST-EMP-002',
                'code_employee' => 'C-002-REV',
                'id_biometrik' => 'BIO-002',
                'employee_name' => 'Budi Example Updated',
                'gender' => 'M',
                'position_name' => 'Manager',
                'pay_type' => 'M',
                'contract' => 'REGULAR',
                'civil_status' => 'M/1',
                'date_hired' => '2025-01-10',
                'date_terminated' => '2025-12-31',
                'date_of_birth' => '1997-05-11',
                'cell_phone' => '081200000000',
                'account_no' => 'ACC-001',
                'identity_card_no' => 'KTP-001',
                'insurance_no' => 'INS-001',
                'no_astek' => 'AST-001',
                'religion' => 'ISLAM',
                'education' => 'S1',
                'remarks' => 'Updated in test',
                'photo' => UploadedFile::fake()->image('employee-updated.png', 400, 500),
            ])
            ->assertRedirect();

        $employee->refresh();

        $this->assertSame('Budi Example Updated', $employee->employee_name);
        $this->assertSame('C-002-REV', $employee->code_employee);
        $this->assertSame('2025-12-31', optional($employee->date_terminated)->toDateString());
        $this->assertNotNull($employee->photo_path);
        $this->assertTrue(File::exists(public_path($employee->photo_path)));

        $this->actingAs($this->admin)
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect();

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }
}
