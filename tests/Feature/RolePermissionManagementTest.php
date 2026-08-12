<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::query()->create([
        'name' => 'RBAC Test Dept',
        'code' => 'RBAC',
        'alias' => 'RBAC',
    ]);
});

function createRbacUser(string $username, ?string $spatieRole = null): User
{
    $user = User::query()->create([
        'name' => "User {$username}",
        'username' => $username,
        'email' => "{$username}@example.test",
        'password' => Hash::make('password'),
        'department_id' => test()->department->id,
        'role' => 'Staff',
    ]);

    if ($spatieRole) {
        $user->assignRole($spatieRole);
    }

    return $user;
}

it('forbids non-admin from managing roles', function () {
    $user = createRbacUser('staff-no-rbac', 'purchasing-staff');

    $this->actingAs($user)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('allows administrator to manage roles and permissions', function () {
    $admin = createRbacUser('admin-rbac', 'administrator');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('permissions.index'))
        ->assertOk();
});

it('creates a role and syncs permissions', function () {
    $admin = createRbacUser('admin-role-create', 'administrator');

    $this->actingAs($admin)
        ->post(route('roles.store'), ['name' => 'custom-auditor'])
        ->assertRedirect(route('roles.index'));

    $role = Role::findByName('custom-auditor');

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'name' => 'custom-auditor',
            'sync_permissions' => '1',
            'permissions' => ['view-products', 'view-suppliers'],
        ])
        ->assertRedirect(route('roles.show', $role));

    expect($role->fresh()->hasPermissionTo('view-products'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('view-suppliers'))->toBeTrue();
});

it('blocks deleting administrator role and roles with users', function () {
    $admin = createRbacUser('admin-role-delete', 'administrator');
    $staff = createRbacUser('staff-with-role', 'purchasing-staff');
    $role = Role::findByName('purchasing-staff');

    $this->actingAs($admin)
        ->delete(route('roles.destroy', Role::findByName('administrator')))
        ->assertRedirect(route('roles.index'));

    expect(Role::findByName('administrator'))->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::findByName('purchasing-staff'))->not->toBeNull();
    expect($staff->fresh()->hasRole('purchasing-staff'))->toBeTrue();
});

it('creates permission and blocks delete when assigned', function () {
    $admin = createRbacUser('admin-perm', 'administrator');

    $this->actingAs($admin)
        ->post(route('permissions.store'), ['name' => 'custom-report-x'])
        ->assertRedirect(route('permissions.index'));

    $permission = Permission::findByName('custom-report-x');
    Role::findByName('purchasing-staff')->givePermissionTo($permission);

    $this->actingAs($admin)
        ->delete(route('permissions.destroy', $permission))
        ->assertRedirect(route('permissions.index'));

    expect(Permission::findByName('custom-report-x'))->not->toBeNull();
});

it('assigns multiple roles and direct permissions to a user', function () {
    $admin = createRbacUser('admin-access', 'administrator');
    $user = createRbacUser('multi-access-user');

    $this->actingAs($admin)
        ->put(route('users.access.update', $user), [
            'roles' => ['purchasing-staff', 'im-staff'],
            'permissions' => ['view-all-prs'],
        ])
        ->assertRedirect(route('users.access.edit', $user));

    $user->refresh();
    expect($user->hasRole('purchasing-staff'))->toBeTrue();
    expect($user->hasRole('im-staff'))->toBeTrue();
    expect($user->can('view-all-prs'))->toBeTrue();
    expect($user->can('view-rr'))->toBeTrue();
});

it('does not auto-assign spatie roles when creating a user', function () {
    $admin = createRbacUser('admin-create-user', 'administrator');

    $this->actingAs($admin)
        ->post(route('user.store'), [
            'name' => 'New Hire',
            'username' => 'newhire',
            'email' => 'newhire@example.test',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'department_id' => $this->department->id,
            'role' => 'Staff',
        ])
        ->assertRedirect();

    $created = User::query()->where('username', 'newhire')->first();
    expect($created)->not->toBeNull();
    expect($created->roles)->toBeEmpty();
});

it('allows direct permission without role to access a gated module', function () {
    $user = createRbacUser('direct-perm-only');
    $user->givePermissionTo('view-products');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertOk();
});

it('forbids users without permission from gated modules', function () {
    $user = createRbacUser('no-product-perm');

    $this->actingAs($user)
        ->get(route('product.index'))
        ->assertForbidden();
});

it('shows via-role badge on access page for role-granted permissions', function () {
    $admin = createRbacUser('admin-via-role-ui', 'administrator');
    $user = createRbacUser('via-role-user', 'purchasing-staff');

    $this->actingAs($admin)
        ->get(route('users.access.edit', $user))
        ->assertOk()
        ->assertSee('Via role')
        ->assertSee('rbac-perm-via-role-text', false)
        ->assertSee('data-bstooltip-toggle="tooltip"', false)
        ->assertSee('title="purchasing-staff"', false)
        ->assertDontSee('Already granted via')
        ->assertSee('Purchase Requisition');
});

it('shows edit link, detail affordance, and create checklist on roles index', function () {
    $admin = createRbacUser('admin-roles-ui', 'administrator');
    $role = Role::findByName('purchasing-staff');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertSee(route('roles.show', $role), false)
        ->assertSee('role-detail-'.$role->id, false)
        ->assertSee('Developer checklist')
        ->assertSee('data-bstooltip-toggle="tooltip"', false);
});

it('lists permissions with users column, create checklist, and without delete controls', function () {
    $admin = createRbacUser('admin-perm-ui', 'administrator');
    $permission = Permission::findByName('view-products');

    $this->actingAs($admin)
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertSee('permission-detail-'.$permission->id, false)
        ->assertSee('>Users</th>', false)
        ->assertSee('Developer checklist')
        ->assertSee('data-bstooltip-toggle="tooltip"', false)
        ->assertDontSee('Delete Permission')
        ->assertDontSee('hapus-'.$permission->id, false);
});

it('shows polished users section on role edit page', function () {
    $admin = createRbacUser('admin-role-users-ui', 'administrator');
    $role = Role::findByName('purchasing-staff');
    createRbacUser('role-member-ui', 'purchasing-staff');

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertSee('Users with this role')
        ->assertSee('rbac-user-list', false)
        ->assertSee('rbac-user-row', false)
        ->assertSee('role-member-ui');
});

it('seeds reset-activity-logs permission for administrator only', function () {
    expect(Permission::findByName('reset-activity-logs'))->not->toBeNull();
    expect(Role::findByName('administrator')->hasPermissionTo('reset-activity-logs'))->toBeTrue();
    expect(Role::findByName('it-manager')->hasPermissionTo('reset-activity-logs'))->toBeFalse();
    expect(Role::findByName('it-staff')->hasPermissionTo('reset-activity-logs'))->toBeFalse();
});

it('prevents it-manager from granting reset-activity-logs via role or user access', function () {
    $itManager = createRbacUser('it-mgr-guard', 'it-manager');
    $target = createRbacUser('target-no-reset');
    $staffRole = Role::findByName('purchasing-staff');
    $adminRole = Role::findByName('administrator');

    $this->actingAs($itManager)
        ->put(route('roles.update', $staffRole), [
            'name' => 'purchasing-staff',
            'sync_permissions' => '1',
            'permissions' => array_merge($staffRole->permissions->pluck('name')->all(), ['reset-activity-logs']),
        ])
        ->assertRedirect();

    expect($staffRole->fresh()->hasPermissionTo('reset-activity-logs'))->toBeFalse();

    $this->actingAs($itManager)
        ->put(route('roles.update', $adminRole), [
            'name' => 'administrator',
            'sync_permissions' => '1',
            'permissions' => ['view-dashboard'],
        ])
        ->assertRedirect();

    expect($adminRole->fresh()->hasPermissionTo('reset-activity-logs'))->toBeTrue();

    $this->actingAs($itManager)
        ->put(route('users.access.update', $target), [
            'roles' => ['purchasing-staff'],
            'permissions' => ['reset-activity-logs'],
        ])
        ->assertRedirect(route('users.access.edit', $target));

    expect($target->fresh()->can('reset-activity-logs'))->toBeFalse();

    $this->actingAs($itManager)
        ->put(route('users.access.update', $target), [
            'roles' => ['administrator'],
            'permissions' => [],
        ])
        ->assertRedirect(route('users.access.edit', $target));

    expect($target->fresh()->hasRole('administrator'))->toBeFalse();
});
