<?php

use App\Models\Department;
use App\Models\Prs;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
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
        ->assertSee('Deliveries')
        ->assertDontSee('dashboard-quick-link', false);
});

it('shows the full administrator dashboard for general-manager', function () {
    $user = makeDashboardUser('general-manager', [
        'name' => 'Office Of The Managing Director',
        'code' => '7054',
        'alias' => 'MD',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="admin"', false)
        ->assertSee('Administrator Dashboard')
        ->assertSee('User Accounts')
        ->assertSee('Canvass Open')
        ->assertSee('SWS Open')
        ->assertDontSee('Executive Dashboard');
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

it('shows the administrator dashboard for it-staff', function () {
    $user = makeDashboardUser('it-staff', [
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
        ->assertDontSee('IT Dashboard');
});

it('shows the administrator dashboard for it-manager', function () {
    $user = makeDashboardUser('it-manager', [
        'name' => 'Information Technology',
        'code' => '7056',
        'alias' => 'IT',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="admin"', false)
        ->assertSee('Administrator Dashboard');
});

it('shows the executive dashboard for MD department users without general-manager role', function () {
    $department = Department::query()->create([
        'name' => 'Office Of The Managing Director',
        'code' => '7054',
        'alias' => 'MD',
    ]);

    $user = User::query()->create([
        'name' => 'MD Staff',
        'username' => 'md-staff-'.uniqid(),
        'email' => 'md-staff-'.uniqid().'@example.test',
        'password' => Hash::make('password'),
        'department_id' => $department->id,
        'role' => 'Staff',
    ]);
    $user->assignRole('purchasing-staff');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="md"', false)
        ->assertSee('Executive Dashboard')
        ->assertSee('Approved PO Value')
        ->assertDontSee('Administrator Dashboard');
});

it('shows the default department dashboard with sws metrics', function () {
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

    DB::table('store_withdrawals')->insert([
        'sws_number' => 'QA-SWS-001',
        'sws_date' => now()->toDateString(),
        'department_id' => $qa->id,
        'department_code' => '7044',
        'type' => 'normal',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="default"', false)
        ->assertSee('Department Dashboard')
        ->assertSee('QA-ONLY-001')
        ->assertSee('QA-SWS-001')
        ->assertSee('Open PRS')
        ->assertSee('SWS Open')
        ->assertSee('Monthly Department PRS')
        ->assertSee('Department PRS Status')
        ->assertSee('Recent Department SWS')
        ->assertDontSee('SWS Approved')
        ->assertDontSee('User Accounts')
        ->assertDontSee('Canvass Open');
});

it('falls back to the default dashboard when the user has no department', function () {
    $user = makeDashboardUser('purchasing-staff');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('data-dashboard-key="default"', false)
        ->assertSee('Department Dashboard')
        ->assertSee('SWS Open');
});

it('limits po status chart data to approved and pending approval', function () {
    $user = makeDashboardUser('purchasing-staff', [
        'name' => 'Purchasing',
        'code' => '7050',
        'alias' => 'PUR',
    ]);

    $supplier = \App\Models\Supplier::query()->create([
        'code' => 'SUP-DASH-PO',
        'name' => 'Dashboard PO Supplier',
        'created_by' => $user->id,
    ]);

    PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'APPROVED',
        'total' => 1000,
        'approved_at' => now(),
    ]);
    PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'PENDING_APPROVAL',
        'total' => 500,
    ]);
    PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $user->id,
        'status' => 'DRAFT',
        'total' => 250,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful();

    $poStatus = $response->viewData('dashboardData')['po_status'];

    expect($poStatus['labels'])->toBe(['PENDING APPROVAL', 'APPROVED'])
        ->and($poStatus['series'])->toBe([1, 1]);
});
