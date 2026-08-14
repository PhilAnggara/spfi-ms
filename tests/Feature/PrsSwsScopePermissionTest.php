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
    expect($im->can('update-all-stores-withdrawal'))->toBeTrue();
    expect($im->can('delete-all-stores-withdrawal'))->toBeTrue();
    expect($im->can('delete-rr'))->toBeTrue();
    expect($plain->can('view-all-stores-withdrawal'))->toBeFalse();

    $plain->givePermissionTo('view-all-stores-withdrawal');
    expect($plain->fresh()->can('view-all-stores-withdrawal'))->toBeTrue();
});

it('requires delete-rr to destroy a receiving report', function () {
    $viewer = createScopeUser('rr-viewer', $this->departmentA->id);
    $viewer->givePermissionTo('view-rr');

    $deleter = createScopeUser('rr-deleter', $this->departmentA->id);
    $deleter->givePermissionTo(['view-rr', 'delete-rr']);

    $supplier = \App\Models\Supplier::query()->create([
        'name' => 'RR Delete Supplier',
        'code' => 'SUP-RR-DEL',
        'created_by' => $deleter->id,
    ]);

    $po = \App\Models\PurchaseOrder::query()->create([
        'supplier_id' => $supplier->id,
        'created_by' => $deleter->id,
        'status' => 'APPROVED',
        'po_number' => 'PO-RR-DEL-001',
    ]);

    $rr = \App\Models\ReceivingReport::query()->create([
        'rr_number' => 'RR-DEL-001',
        'purchase_order_id' => $po->id,
        'received_date' => now()->toDateString(),
        'created_by' => $deleter->id,
    ]);

    $this->actingAs($viewer)
        ->delete(route('receiving-reports.destroy', $rr))
        ->assertForbidden();

    $this->actingAs($deleter)
        ->from(route('receiving-reports.index'))
        ->delete(route('receiving-reports.destroy', $rr))
        ->assertRedirect(route('receiving-reports.index'));

    expect(\App\Models\ReceivingReport::query()->find($rr->id))->toBeNull();
});

it('does not seed obsolete general or prs crud permissions', function () {
    foreach ([
        'view-dashboard',
        'export-report',
        'print-document',
        'view-prs',
        'create-prs',
        'update-prs',
        'delete-prs',
        'view-stores-withdrawal',
        'create-stores-withdrawal',
        'update-stores-withdrawal',
        'delete-stores-withdrawal',
    ] as $obsolete) {
        expect(\Spatie\Permission\Models\Permission::query()->where('name', $obsolete)->exists())->toBeFalse();
    }
});
