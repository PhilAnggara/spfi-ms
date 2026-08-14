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
        ->assertSee('rbac-perm-matrix', false)
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

it('lists permissions with users column and without add or rename controls', function () {
    $admin = createRbacUser('admin-perm-ui', 'administrator');
    $permission = Permission::findByName('view-products');

    $this->actingAs($admin)
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertSee('permission-detail-'.$permission->id, false)
        ->assertSee('>Users</th>', false)
        ->assertSee('data-bstooltip-toggle="tooltip"', false)
        ->assertDontSee('Add Permission')
        ->assertDontSee('Rename Permission')
        ->assertDontSee('Delete Permission')
        ->assertDontSee('hapus-'.$permission->id, false);
});

it('shows a searchable permission matrix on the role edit page', function () {
    $admin = createRbacUser('admin-role-matrix', 'administrator');
    $role = Role::findByName('purchasing-staff');

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertSee('Search permissions')
        ->assertSee('>View</th>', false)
        ->assertSee('>Create</th>', false)
        ->assertSee('>Update</th>', false)
        ->assertSee('>Delete</th>', false)
        ->assertSee('rbac-perm-matrix', false)
        ->assertDontSee('<code class="small">view-products</code>', false);
});

it('shows a searchable permission matrix on the user access page', function () {
    $admin = createRbacUser('admin-access-matrix', 'administrator');
    $user = createRbacUser('access-matrix-user', 'purchasing-staff');

    $this->actingAs($admin)
        ->get(route('users.access.edit', $user))
        ->assertOk()
        ->assertSee('Search permissions')
        ->assertSee('rbac-perm-matrix', false)
        ->assertSee('Via role')
        ->assertSee('Purchase Requisition');
});

it('seeds renamed crud permissions and drops legacy names', function () {
    expect(Permission::query()->where('name', 'edit-users')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'edit-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'approve-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'manage-uom')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'manage-user-access')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'manage-active-sessions')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'manage-supplier-comparison')->exists())->toBeFalse();

    expect(Permission::findByName('update-users'))->not->toBeNull();
    expect(Permission::findByName('update-all-prs'))->not->toBeNull();
    expect(Permission::findByName('update-department-prs'))->not->toBeNull();
    expect(Permission::findByName('delete-all-prs'))->not->toBeNull();
    expect(Permission::findByName('view-all-stores-withdrawal'))->not->toBeNull();
    expect(Permission::findByName('update-all-stores-withdrawal'))->not->toBeNull();
    expect(Permission::findByName('delete-all-stores-withdrawal'))->not->toBeNull();
    expect(Permission::findByName('delete-rr'))->not->toBeNull();
    expect(Permission::findByName('update-all-po'))->not->toBeNull();
    expect(Permission::findByName('assign-canvasser'))->not->toBeNull();
    expect(Permission::findByName('create-uom'))->not->toBeNull();
    expect(Permission::findByName('assign-user-access'))->not->toBeNull();
    expect(Permission::findByName('view-active-sessions'))->not->toBeNull();
    expect(Permission::findByName('force-logout-users'))->not->toBeNull();
    expect(Permission::findByName('select-supplier-comparison'))->not->toBeNull();
    expect(Permission::findByName('view-roles'))->not->toBeNull();
    expect(Permission::findByName('view-permissions'))->not->toBeNull();

    expect(Permission::query()->where('name', 'view-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'create-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'update-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'delete-prs')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'view-stores-withdrawal')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'view-dashboard')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'export-report')->exists())->toBeFalse();
    expect(Permission::query()->where('name', 'print-document')->exists())->toBeFalse();

    expect(Role::findByName('it-staff')->hasPermissionTo('create-uom'))->toBeTrue();
    expect(Role::findByName('hrd-staff')->hasPermissionTo('delete-employees'))->toBeTrue();
    expect(Role::findByName('purchasing-manager')->hasPermissionTo('assign-canvasser'))->toBeTrue();
    expect(Role::findByName('purchasing-manager')->hasPermissionTo('view-all-prs'))->toBeTrue();
    expect(Role::findByName('purchasing-manager')->hasPermissionTo('update-all-prs'))->toBeFalse();
    expect(Role::findByName('im-staff')->hasPermissionTo('delete-rr'))->toBeTrue();
    expect(Role::findByName('im-staff')->hasPermissionTo('update-all-stores-withdrawal'))->toBeTrue();
    expect(Role::findByName('im-staff')->hasPermissionTo('delete-all-stores-withdrawal'))->toBeTrue();
    expect(Role::findByName('administrator')->hasPermissionTo('update-all-po'))->toBeTrue();
    expect(Role::findByName('production-manager')->hasPermissionTo('assign-canvasser'))->toBeFalse();
    expect(Role::findByName('engineering-manager')->hasPermissionTo('assign-canvasser'))->toBeFalse();
});

it('renames edit-users in place when reseeding', function () {
    $permission = Permission::findByName('update-users');
    $originalId = $permission->id;
    $permission->name = 'edit-users';
    $permission->save();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);

    expect(Permission::query()->where('name', 'edit-users')->exists())->toBeFalse();
    expect(Permission::findByName('update-users')->id)->toBe($originalId);
});

it('forbids production-manager from canvasser assignment', function () {
    $manager = createRbacUser('prod-mgr-assign', 'production-manager');

    $this->actingAs($manager)
        ->get(route('prs.approval.index'))
        ->assertForbidden();
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
            'permissions' => ['view-users'],
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
