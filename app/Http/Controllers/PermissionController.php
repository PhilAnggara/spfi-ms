<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Support\PermissionModuleGroups;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::query()
            ->with(['roles.users', 'users'])
            ->withCount('roles')
            ->orderBy('name')
            ->get();

        $permissionGroups = PermissionModuleGroups::group($permissions);

        $permissionUserSources = [];
        foreach ($permissions as $permission) {
            $directIds = $permission->users->pluck('id')->all();
            $viaRoleUsers = $permission->roles
                ->flatMap(fn ($role) => $role->users)
                ->unique('id');

            $userRoleNames = [];
            foreach ($permission->roles as $role) {
                foreach ($role->users as $roleUser) {
                    $userRoleNames[$roleUser->id][] = $role->name;
                }
            }

            $rows = [];
            foreach ($viaRoleUsers as $user) {
                $rows[$user->id] = [
                    'user' => $user,
                    'via_role' => true,
                    'direct' => in_array($user->id, $directIds, true),
                    'role_names' => array_values(array_unique($userRoleNames[$user->id] ?? [])),
                ];
            }
            foreach ($permission->users as $user) {
                if (! isset($rows[$user->id])) {
                    $rows[$user->id] = [
                        'user' => $user,
                        'via_role' => false,
                        'direct' => true,
                        'role_names' => [],
                    ];
                } else {
                    $rows[$user->id]['direct'] = true;
                }
            }

            $permissionUserSources[$permission->id] = collect($rows)->values();
        }

        return view('pages.permissions.index', compact(
            'permissions',
            'permissionGroups',
            'permissionUserSources',
        ));
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permission created successfully.');
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->name = $request->validated('name');
        $permission->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permission updated successfully. Update any hard-coded references in routes or views if the name changed.');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('permissions.index');
    }

    public function edit(Permission $permission): RedirectResponse
    {
        return redirect()->route('permissions.index');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists()) {
            return redirect()->route('permissions.index')->with('error', 'Cannot delete a permission that is assigned to roles.');
        }

        if ($permission->users()->exists()) {
            return redirect()->route('permissions.index')->with('error', 'Cannot delete a permission that is assigned directly to users.');
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully.');
    }
}
