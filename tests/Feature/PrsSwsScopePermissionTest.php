<?php

use App\Models\Department;
use App\Models\Prs;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    $this->departmentA = Department::query()->create([
        'name' => 'Dept A',
        'code' => 'DEPA',
        'alias' => 'DA',
    ]);
    $this->departmentB = Department::query()->create([
        'name' => 'Dept B',
        'code' => 'DEPB',
        'alias' => 'DB',
    ]);
});

function createScopeUser(string $username, int $departmentId, ?string $spatieRole = null): User
{
    $user = User::query()->create([
        'name' => "Scope {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => $departmentId,
        'role' => 'Staff',
    ]);

    if ($spatieRole) {
        $user->assignRole($spatieRole);
    }

    return $user;
}

it('allows authenticated users without roles to open prs and stores withdrawals', function () {
    $user = createScopeUser('no-role-user', $this->departmentA->id);

    expect($user->roles)->toBeEmpty();

    $this->actingAs($user)
        ->get(route('prs.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('stores-withdrawals.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('prs.create'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('stores-withdrawals.create'))
        ->assertOk();
});

it('grants view-all-prs via role and via direct permission', function () {
    $peer = createScopeUser('prs-peer', $this->departmentA->id);
    $otherDeptUser = createScopeUser('prs-other-dept', $this->departmentB->id);

    Prs::query()->create([
        'user_id' => $otherDeptUser->id,
        'department_id' => $this->departmentB->id,
        'prs_number' => 'DEPB0000001',
        'prs_date' => now()->toDateString(),
        'date_needed' => now()->addWeek()->toDateString(),
        'status' => 'OPEN',
        'is_capex' => false,
    ]);

    expect($peer->can('view-all-prs'))->toBeFalse();

    $peer->givePermissionTo('view-all-prs');
    expect($peer->fresh()->can('view-all-prs'))->toBeTrue();

    $purchasing = createScopeUser('prs-purch', $this->departmentA->id, 'purchasing-staff');
    expect($purchasing->can('view-all-prs'))->toBeTrue();
});

it('grants view-all-stores-withdrawal to im roles', function () {
    $im = createScopeUser('im-scope', $this->departmentA->id, 'im-staff');
    $plain = createScopeUser('plain-sws', $this->departmentA->id);

    expect($im->can('view-all-stores-withdrawal'))->toBeTrue();
    expect($plain->can('view-all-stores-withdrawal'))->toBeFalse();

    $plain->givePermissionTo('view-all-stores-withdrawal');
    expect($plain->fresh()->can('view-all-stores-withdrawal'))->toBeTrue();
});
