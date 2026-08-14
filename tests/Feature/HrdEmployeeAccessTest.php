<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Human Resources Development',
        'code' => '7048',
        'alias' => 'HRD',
    ]);

    $this->employeeDepartment = EmployeeDepartment::query()->create([
        'code' => '70482',
        'old_code' => '7048',
        'name' => 'HUMAN RESOURCES TEST',
    ]);
});

function createHrdEmployeeUser(string $role): User
{
    $user = User::query()->create([
        'name' => "HRD {$role} User",
        'username' => "hrd-{$role}",
        'email' => "hrd-{$role}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user;
}

it('allows each hrd role full employee crud and shows the employee menu', function (string $role) {
    $user = createHrdEmployeeUser($role);

    $this->actingAs($user)
        ->get(route('employees.index'))
        ->assertSuccessful()
        ->assertSee('Employee')
        ->assertDontSee('>User</a>', false)
        ->assertDontSee('Active Sessions');

    $this->actingAs($user)
        ->post(route('employees.store'), [
            'employee_department_id' => $this->employeeDepartment->id,
            'employee_id' => "HRD-{$role}-001",
            'code_employee' => "C-{$role}-001",
            'employee_name' => "Employee {$role}",
            'gender' => 'F',
            'position_name' => 'HR Admin',
        ])
        ->assertRedirect();

    $employee = Employee::query()->where('employee_id', "HRD-{$role}-001")->firstOrFail();

    $this->actingAs($user)
        ->put(route('employees.update', $employee), [
            'employee_department_id' => $this->employeeDepartment->id,
            'employee_id' => "HRD-{$role}-001",
            'code_employee' => "C-{$role}-001",
            'employee_name' => "Employee {$role} Updated",
            'gender' => 'F',
            'position_name' => 'HR Supervisor',
        ])
        ->assertRedirect();

    expect($employee->fresh()->employee_name)->toBe("Employee {$role} Updated");

    $this->actingAs($user)
        ->delete(route('employees.destroy', $employee))
        ->assertRedirect();

    $this->assertSoftDeleted('employees', [
        'id' => $employee->id,
    ]);
})->with([
    'hrd-manager',
    'hrd-supervisor',
    'hrd-staff',
]);

it('forbids hrd roles from other master pages', function (string $role) {
    $user = createHrdEmployeeUser($role);

    $this->actingAs($user)
        ->get(route('user.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('active-sessions.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('product-category.index'))
        ->assertForbidden();
})->with([
    'hrd-manager',
    'hrd-supervisor',
    'hrd-staff',
]);
