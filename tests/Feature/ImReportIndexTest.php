<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'Inventory Management',
        'code' => '7042',
        'alias' => 'IM',
    ]);
});

function createImReportsUser(string $role, string $username): User
{
    $user = User::query()->create([
        'name' => "IM Reports {$role}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);
    $user->assignRole($role);

    return $user;
}

it('allows im roles to view the im reports page', function (string $role) {
    $user = createImReportsUser($role, "im-reports-{$role}");

    $this->actingAs($user)
        ->get(route('im.reports.index'))
        ->assertOk()
        ->assertSee('IM Reports')
        ->assertSee('Stock Inventory per Category')
        ->assertSee('Transaction Report per Category')
        ->assertSee('Receiving Report Register')
        ->assertSee('Stores Withdrawal Slip Register')
        ->assertSee('Transfer Slip Register')
        ->assertSee('Delivery Receipt Register');
})->with([
    'administrator',
    'im-manager',
    'im-supervisor',
    'im-staff',
]);

it('forbids unrelated roles from viewing im reports', function () {
    $user = createImReportsUser('purchasing-staff', 'im-reports-purchasing');

    $this->actingAs($user)
        ->get(route('im.reports.index'))
        ->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('im.reports.index'))
        ->assertRedirect(route('login'));
});

it('shows all-type normal and others options on transfer register', function () {
    $user = createImReportsUser('im-staff', 'im-reports-ts-types');

    $this->actingAs($user)
        ->get(route('im.reports.index'))
        ->assertOk()
        ->assertSee('All type')
        ->assertSee('Normal')
        ->assertSee('Others')
        ->assertDontSee('Finished Goods');
});
