<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\User;
use App\Support\PermissionModuleGroups;
use App\Support\RbacGuards;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserAccessController extends Controller
{
    public function edit(User $user): View
    {
        $user->load(['roles.permissions', 'permissions']);
        $roles = Role::query()->orderBy('name')->get();
        $permissions = Permission::query()->orderBy('name')->get();
        $permissionGroups = PermissionModuleGroups::group($permissions);

        $viaRolePermissionNames = $user->getPermissionsViaRoles()->pluck('name')->all();

        $viaRoleSources = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $viaRoleSources[$permission->name][] = $role->name;
            }
        }

        return view('pages.users.access', compact(
            'user',
            'roles',
            'permissionGroups',
            'viaRolePermissionNames',
            'viaRoleSources',
        ));
    }

    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($user->hasRole(RbacGuards::PROTECTED_ROLE) && ! RbacGuards::actorIsAdministrator($actor)) {
            return redirect()
                ->route('users.access.edit', $user)
                ->with('error', 'Only administrators can modify administrator access.');
        }

        $roles = RbacGuards::sanitizeRoleNames(
            $actor,
            $request->validated('roles') ?? [],
            $user->roles->pluck('name'),
        );

        if ($roles === null) {
            return redirect()
                ->route('users.access.edit', $user)
                ->with('error', 'Only administrators can assign or remove the administrator role.');
        }

        $permissions = RbacGuards::sanitizePermissionNames(
            $actor,
            $request->validated('permissions') ?? [],
            $user->getDirectPermissions()->pluck('name'),
        );

        $user->syncRoles($roles);
        $user->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('users.access.edit', $user)
            ->with('success', "Access for {$user->name} has been updated.");
    }
}
