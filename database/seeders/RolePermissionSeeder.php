<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions_users = [
            'create-users',
            'view-users',
            'edit-users',
            'delete-users',
        ];

        $permissions_prs = [
            'create-prs',
            'view-prs',
            'edit-prs',
            'delete-prs',
            'approve-prs',
        ];

        $permissions_canvassing = [
            'assign-canvaser',
            'view-canvassing',
            'update-canvassing',
        ];

        $permissions_po = [
            'create-po',
            'view-po',
            'submit-po',
            'approve-po',
            'cancel-po',
            'view-po-progress',
        ];

        $permissions_rr = [
            'create-rr',
            'view-rr',
            'update-rr',
        ];

        $permissions_general = [
            'view-dashboard',
            'export-report',
            'print-document',
        ];

        $all_permissions = array_unique(array_merge(
            $permissions_users,
            $permissions_prs,
            $permissions_canvassing,
            $permissions_po,
            $permissions_rr,
            $permissions_general
        ));

        foreach ($all_permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'administrator',
            'general-manager',
            'it-manager',
            'it-staff',
            'purchasing-manager',
            'purchasing-staff',
            'im-manager',
            'im-supervisor',
            'im-staff',
            'finance-manager',
            'finance-supervisor',
            'finance-staff',
            'accounting-manager',
            'accounting-supervisor',
            'accounting-staff',
            'production-manager',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $rolePermissions = [
            'administrator' => $all_permissions,
            'general-manager' => [
                'view-prs',
                'view-po',
                'view-po-progress',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'it-manager' => $all_permissions,
            'it-staff' => [
                'view-dashboard',
                'print-document',
            ],
            'purchasing-manager' => [
                'approve-prs',
                'view-prs',
                'assign-canvaser',
                'view-canvassing',
                'approve-po',
                'view-po',
                'view-po-progress',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'purchasing-staff' => [
                'view-canvassing',
                'update-canvassing',
                'create-po',
                'view-po',
                'submit-po',
                'cancel-po',
                'view-po-progress',
                'create-prs',
                'view-prs',
                'edit-prs',
                'delete-prs',
                'view-dashboard',
                'print-document',
            ],
            'im-manager' => [
                'create-rr',
                'view-rr',
                'update-rr',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'im-supervisor' => [
                'create-rr',
                'view-rr',
                'update-rr',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'im-staff' => [
                'create-rr',
                'view-rr',
                'update-rr',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'finance-manager' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'finance-supervisor' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'finance-staff' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'print-document',
            ],
            'accounting-manager' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'accounting-supervisor' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'accounting-staff' => [
                'view-prs',
                'view-po',
                'view-rr',
                'view-dashboard',
                'print-document',
            ],
            'production-manager' => [
                'create-prs',
                'view-prs',
                'edit-prs',
                'approve-prs',
                'view-po-progress',
                'view-dashboard',
                'print-document',
            ],
        ];

        foreach ($rolePermissions as $role => $permissions) {
            Role::findByName($role)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
