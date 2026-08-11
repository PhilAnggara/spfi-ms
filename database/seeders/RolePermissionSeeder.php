<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
            'assign-canvasser',
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

        $permissions_transfer = [
            'create-transfer',
            'view-transfer',
            'update-transfer',
            'delete-transfer',
        ];

        $permissions_delivery = [
            'create-delivery',
            'view-delivery',
            'update-delivery',
            'delete-delivery',
        ];

        $permissions_stock_adjustment = [
            'create-stock-adjustment',
            'view-stock-adjustment',
            'delete-stock-adjustment',
        ];

        $permissions_opening_balance = [
            'create-opening-balance-correction',
            'view-opening-balance-correction',
            'delete-opening-balance-correction',
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
            $permissions_transfer,
            $permissions_delivery,
            $permissions_stock_adjustment,
            $permissions_opening_balance,
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
            'engineering-manager',
            'engineering-supervisor',
            'engineering-staff',
            'hrd-manager',
            'hrd-supervisor',
            'hrd-staff',
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
                'assign-canvasser',
                'view-canvassing',
                'approve-po',
                'view-po',
                'view-po-progress',
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
                'create-transfer',
                'view-transfer',
                'update-transfer',
                'delete-transfer',
                'create-delivery',
                'view-delivery',
                'update-delivery',
                'delete-delivery',
                'create-stock-adjustment',
                'view-stock-adjustment',
                'delete-stock-adjustment',
                'create-opening-balance-correction',
                'view-opening-balance-correction',
                'delete-opening-balance-correction',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'im-supervisor' => [
                'create-rr',
                'view-rr',
                'update-rr',
                'create-transfer',
                'view-transfer',
                'update-transfer',
                'delete-transfer',
                'create-delivery',
                'view-delivery',
                'update-delivery',
                'delete-delivery',
                'create-stock-adjustment',
                'view-stock-adjustment',
                'delete-stock-adjustment',
                'create-opening-balance-correction',
                'view-opening-balance-correction',
                'delete-opening-balance-correction',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'im-staff' => [
                'create-rr',
                'view-rr',
                'update-rr',
                'create-transfer',
                'view-transfer',
                'update-transfer',
                'delete-transfer',
                'create-delivery',
                'view-delivery',
                'update-delivery',
                'delete-delivery',
                'create-stock-adjustment',
                'view-stock-adjustment',
                'delete-stock-adjustment',
                'create-opening-balance-correction',
                'view-opening-balance-correction',
                'delete-opening-balance-correction',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'finance-manager' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'finance-supervisor' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'finance-staff' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'accounting-manager' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'accounting-supervisor' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'accounting-staff' => [
                'view-prs',
                'view-po',
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
            'engineering-manager' => [
                'create-prs',
                'view-prs',
                'edit-prs',
                'approve-prs',
                'view-po-progress',
                'view-dashboard',
                'print-document',
            ],
            'engineering-supervisor' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'engineering-staff' => [
                'view-prs',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'hrd-manager' => [
                'view-dashboard',
                'print-document',
            ],
            'hrd-supervisor' => [
                'view-dashboard',
                'print-document',
            ],
            'hrd-staff' => [
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
