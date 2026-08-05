<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'General',
        'code' => '1000',
        'alias' => 'GEN',
    ]);
});

function createAccessMatrixUser(string $role, string $username): User
{
    $user = User::query()->create([
        'name' => "Access Matrix {$role}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);
    $user->assignRole($role);

    return $user;
}

it('allows purchasing roles to view purchasing reports', function (string $role) {
    $user = createAccessMatrixUser($role, "purch-reports-{$role}");

    $this->actingAs($user)
        ->get(route('procurement.reports.index'))
        ->assertOk()
        ->assertSee('Purchasing Reports');
})->with([
    'administrator',
    'purchasing-manager',
    'purchasing-staff',
]);

it('forbids non-purchasing roles from viewing purchasing reports', function (string $role) {
    $user = createAccessMatrixUser($role, "forbid-purch-reports-{$role}");

    $this->actingAs($user)
        ->get(route('procurement.reports.index'))
        ->assertForbidden();
})->with([
    'general-manager',
    'finance-manager',
    'finance-staff',
    'accounting-staff',
    'im-staff',
]);

it('allows finance and accounting roles to view accounting reports', function (string $role) {
    $user = createAccessMatrixUser($role, "acct-reports-{$role}");

    $this->actingAs($user)
        ->get(route('accounting.reports.index'))
        ->assertOk()
        ->assertSee('Accounting Reports');
})->with([
    'administrator',
    'finance-manager',
    'finance-supervisor',
    'finance-staff',
    'accounting-manager',
    'accounting-supervisor',
    'accounting-staff',
]);

it('forbids purchasing manager from viewing accounting reports', function () {
    $user = createAccessMatrixUser('purchasing-manager', 'forbid-acct-reports-purch-mgr');

    $this->actingAs($user)
        ->get(route('accounting.reports.index'))
        ->assertForbidden();
});

it('allows finance staff to view doc entry like accounting', function () {
    $user = createAccessMatrixUser('finance-staff', 'finance-doc-entry');

    $this->actingAs($user)
        ->get(route('accounting.doc-entries.index'))
        ->assertOk();
});

it('allows im roles to view im warehouse documents', function (string $role) {
    $user = createAccessMatrixUser($role, "im-docs-{$role}");

    $this->actingAs($user)
        ->get(route('im.reports.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('receiving-reports.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('transfer-slips.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('deliveries.index'))
        ->assertOk();
})->with([
    'administrator',
    'im-manager',
    'im-supervisor',
    'im-staff',
]);

it('forbids non-im roles from viewing rr transfer and delivery', function (string $role) {
    $user = createAccessMatrixUser($role, "forbid-im-docs-{$role}");

    $this->actingAs($user)
        ->get(route('receiving-reports.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('transfer-slips.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('deliveries.index'))
        ->assertForbidden();
})->with([
    'purchasing-manager',
    'finance-staff',
    'accounting-staff',
    'general-manager',
    'production-manager',
    'engineering-staff',
]);

it('allows any authenticated user to view global menus', function () {
    $user = createAccessMatrixUser('engineering-staff', 'global-menus-user');

    $this->actingAs($user)
        ->get(route('prs.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('stores-withdrawals.index'))
        ->assertOk();
});

it('forbids engineering staff from all department reports', function () {
    $user = createAccessMatrixUser('engineering-staff', 'forbid-all-reports');

    $this->actingAs($user)
        ->get(route('procurement.reports.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('accounting.reports.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('im.reports.index'))
        ->assertForbidden();
});
