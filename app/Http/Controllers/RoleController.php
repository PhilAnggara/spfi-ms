<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Support\PermissionModuleGroups;
use App\Support\RbacGuards;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->with(['permissions', 'users.department'])
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        $rolePermissionGroups = [];
        foreach ($roles as $role) {
            $rolePermissionGroups[$role->id] = PermissionModuleGroups::group($role->permissions);
        }

        return view('pages.roles.index', compact('roles', 'rolePermissionGroups'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role): View
    {
        $role->load(['permissions', 'users.department']);
        $permissions = Permission::query()->orderBy('name')->get();
        $permissionGroups = PermissionModuleGroups::group($permissions);

        return view('pages.roles.show', compact('role', 'permissionGroups'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === RbacGuards::PROTECTED_ROLE && $request->validated('name') !== RbacGuards::PROTECTED_ROLE) {
            return redirect()->back()->with('error', 'The administrator role name cannot be changed.');
        }

        if (
            $role->name === RbacGuards::PROTECTED_ROLE
            && $request->boolean('sync_permissions')
            && ! RbacGuards::actorIsAdministrator($request->user())
        ) {
            return redirect()->back()->with('error', 'Only administrators can change the administrator role permissions.');
        }

        $role->name = $request->validated('name');
        $role->save();

        if ($request->boolean('sync_permissions')) {
            $permissions = RbacGuards::sanitizePermissionNames(
                $request->user(),
                $request->validated('permissions') ?? [],
                $role->permissions()->pluck('name'),
            );
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.show', $role)->with('success', 'Role updated successfully.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('roles.index');
    }

    public function edit(Role $role): RedirectResponse
    {
        return redirect()->route('roles.show', $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === RbacGuards::PROTECTED_ROLE) {
            return redirect()->route('roles.index')->with('error', 'The administrator role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'Cannot delete a role that is still assigned to users.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }
}
