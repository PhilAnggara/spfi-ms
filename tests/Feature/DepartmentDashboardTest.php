<?php

use App\Models\Department;
use App\Models\Prs;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeDashboardUser(string $role, ?array $departmentAttributes = null): User
{
    $departmentId = null;

    if ($departmentAttributes !== null) {
        $department = Department::query()->create($departmentAttributes);
        $departmentId = $department->id;
    }

    $user = User::query()->create([
        'name' => 'Dashboard User '.$role,
        'username' => 'dash-'.str_replace('-', '', $role).'-'.uniqid(),
        'email' => 'dash-'.$role.'-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $departmentId,
        'role' => 'Staff',
    ]);

    $user->assignRole($role);

    return $user->fresh();
}

it('shows the full administrator dashboard regardless of department', function () {
    $user = makeDashboardUser('administrator', [
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="admin"', false)
        ->assertSee('Administrator Dashboard')
        ->assertSee('User Accounts')
        ->assertSee('Canvass Open')
        ->assertSee('SWS Open')
        ->assertSee('TS Pending')
        ->assertSee('Deliveries');
});

it('shows the purchasing dashboard for PUR department users', function () {
    $user = makeDashboardUser('purchasing-staff', [
        'name' => 'Purchasing',
        'code' => '7050',
        'alias' => 'PUR',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="purchasing"', false)
        ->assertSee('Purchasing Dashboard')
        ->assertSee('Canvass Open')
        ->assertSee('PO Pending Approval')
        ->assertDontSee('Administrator Dashboard')
        ->assertDontSee('SWS Open');
});

it('shows the inventory dashboard for IM department users', function () {
    $user = makeDashboardUser('im-staff', [
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="im"', false)
        ->assertSee('Inventory Management Dashboard')
        ->assertSee('SWS Open')
        ->assertSee('TS Pending')
        ->assertDontSee('User Accounts');
});

it('shows the finance dashboard for FIN department users', function () {
    $user = makeDashboardUser('finance-staff', [
        'name' => 'Finance',
        'code' => '7052',
        'alias' => 'FIN',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="finance"', false)
        ->assertSee('Finance Dashboard')
        ->assertSee('Approved PO Value');
});

it('shows the engineering dashboard filtered to engineering department prs', function () {
    $engineering = Department::query()->create([
        'name' => 'Engineering',
        'code' => '7046',
        'alias' => 'ENG',
    ]);

    $other = Department::query()->create([
        'name' => 'Quality Assurance',
        'code' => '7044',
        'alias' => 'QA',
    ]);

    $user = User::query()->create([
        'name' => 'Engineering Staff',
        'username' => 'eng-dash-'.uniqid(),
        'email' => 'eng-dash-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $engineering->id,
        'role' => 'Staff',
    ]);
    $user->assignRole('engineering-staff');

    Prs::query()->create([
        'prs_number' => 'ENG-PRS-001',
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'user_id' => $user->id,
        'department_id' => $engineering->id,
        'status' => 'REQUESTED',
        'is_capex' => false,
    ]);

    Prs::query()->create([
        'prs_number' => 'QA-PRS-001',
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'user_id' => $user->id,
        'department_id' => $other->id,
        'status' => 'REQUESTED',
        'is_capex' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="engineering"', false)
        ->assertSee('Engineering Dashboard')
        ->assertSee('ENG-PRS-001')
        ->assertDontSee('QA-PRS-001');
});

it('shows the it dashboard for non-admin it staff', function () {
    $user = makeDashboardUser('it-staff', [
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="it"', false)
        ->assertSee('IT Dashboard')
        ->assertSee('User Accounts')
        ->assertDontSee('Administrator Dashboard')
        ->assertDontSee('Canvass Open');
});

it('shows the executive dashboard for MD department users', function () {
    $user = makeDashboardUser('general-manager', [
        'name' => 'Office Of The Managing Director',
        'code' => '7054',
        'alias' => 'MD',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="md"', false)
        ->assertSee('Executive Dashboard')
        ->assertSee('Approved PO Value')
        ->assertDontSee('Administrator Dashboard');
});

it('shows the default department dashboard for other departments', function () {
    $qa = Department::query()->create([
        'name' => 'Quality Assurance',
        'code' => '7044',
        'alias' => 'QA',
    ]);

    $user = User::query()->create([
        'name' => 'QA Staff',
        'username' => 'qa-dash-'.uniqid(),
        'email' => 'qa-dash-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $qa->id,
        'role' => 'Staff',
    ]);
    $user->assignRole('production-manager');

    Prs::query()->create([
        'prs_number' => 'QA-ONLY-001',
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addDays(7)->toDateString(),
        'user_id' => $user->id,
        'department_id' => $qa->id,
        'status' => 'REQUESTED',
        'is_capex' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="default"', false)
        ->assertSee('Department Dashboard')
        ->assertSee('QA-ONLY-001')
        ->assertSee('Open PRS')
        ->assertDontSee('User Accounts')
        ->assertDontSee('Canvass Open');
});

it('falls back to the default dashboard when the user has no department', function () {
    $user = makeDashboardUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="default"', false)
        ->assertSee('Department Dashboard');
});
