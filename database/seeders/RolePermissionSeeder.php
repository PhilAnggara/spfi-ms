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
     *
     * Assignments are additive (givePermissionTo) so re-seeding does not revoke
     * permissions granted through the Role & Permission management UI.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsUsers = [
            'create-users',
            'view-users',
            'edit-users',
            'delete-users',
            'manage-active-sessions',
            'reset-activity-logs',
            'manage-user-access',
        ];

        $permissionsPrs = [
            'create-prs',
            'view-prs',
            'edit-prs',
            'delete-prs',
            'approve-prs',
            'view-all-prs',
        ];

        $permissionsCanvassing = [
            'assign-canvasser',
            'view-canvassing',
            'update-canvassing',
        ];

        $permissionsPo = [
            'create-po',
            'view-po',
            'submit-po',
            'approve-po',
            'cancel-po',
            'view-po-progress',
        ];

        $permissionsRr = [
            'create-rr',
            'view-rr',
            'update-rr',
        ];

        $permissionsTransfer = [
            'create-transfer',
            'view-transfer',
            'update-transfer',
            'delete-transfer',
        ];

        $permissionsDelivery = [
            'create-delivery',
            'view-delivery',
            'update-delivery',
            'delete-delivery',
        ];

        $permissionsStockAdjustment = [
            'create-stock-adjustment',
            'view-stock-adjustment',
            'delete-stock-adjustment',
        ];

        $permissionsOpeningBalance = [
            'create-opening-balance-correction',
            'view-opening-balance-correction',
            'delete-opening-balance-correction',
        ];

        $permissionsStoresWithdrawal = [
            'view-stores-withdrawal',
            'create-stores-withdrawal',
            'update-stores-withdrawal',
            'delete-stores-withdrawal',
            'view-all-stores-withdrawal',
        ];

        $permissionsMaster = [
            'view-products',
            'create-products',
            'update-products',
            'delete-products',
            'view-suppliers',
            'create-suppliers',
            'update-suppliers',
            'delete-suppliers',
            'view-purchase-history',
            'view-product-categories',
            'manage-product-categories',
            'view-uom',
            'manage-uom',
            'view-buyers',
            'manage-buyers',
            'view-currencies',
            'manage-currencies',
            'view-batches',
            'manage-batches',
            'view-fish-suppliers',
            'manage-fish-suppliers',
            'view-vessels',
            'manage-vessels',
            'view-fish',
            'manage-fish',
            'view-employees',
            'manage-employees',
            'view-accounting-master',
            'manage-accounting-master',
        ];

        $permissionsReportsOps = [
            'view-procurement-reports',
            'view-im-reports',
            'view-accounting-reports',
            'view-doc-entries',
            'update-doc-entries',
            'view-exchange-rates',
            'create-exchange-rates',
            'view-supplier-comparison',
            'manage-supplier-comparison',
        ];

        $permissionsRbac = [
            'manage-roles',
            'manage-permissions',
        ];

        $permissionsGeneral = [
            'view-dashboard',
            'export-report',
            'print-document',
        ];

        $allPermissions = array_values(array_unique(array_merge(
            $permissionsUsers,
            $permissionsPrs,
            $permissionsCanvassing,
            $permissionsPo,
            $permissionsRr,
            $permissionsTransfer,
            $permissionsDelivery,
            $permissionsStockAdjustment,
            $permissionsOpeningBalance,
            $permissionsStoresWithdrawal,
            $permissionsMaster,
            $permissionsReportsOps,
            $permissionsRbac,
            $permissionsGeneral,
        )));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
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
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $itMasterReadWrite = [
            'view-products',
            'create-products',
            'update-products',
            'delete-products',
            'view-suppliers',
            'create-suppliers',
            'update-suppliers',
            'delete-suppliers',
            'view-purchase-history',
            'view-product-categories',
            'manage-product-categories',
            'view-uom',
            'manage-uom',
            'view-buyers',
            'manage-buyers',
            'view-currencies',
            'manage-currencies',
            'view-batches',
            'manage-batches',
            'view-fish-suppliers',
            'manage-fish-suppliers',
            'view-vessels',
            'manage-vessels',
            'view-fish',
            'manage-fish',
            'view-accounting-master',
            'manage-accounting-master',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            'manage-active-sessions',
            'view-employees',
            'manage-employees',
        ];

        $imProductCreate = ['view-products', 'create-products'];
        $imProductManage = ['view-products', 'create-products', 'update-products', 'delete-products'];

        $financeAccountingOps = [
            'view-accounting-reports',
            'view-doc-entries',
            'update-doc-entries',
            'view-exchange-rates',
        ];

        $rolePermissions = [
            'administrator' => $allPermissions,
            'general-manager' => [
                'view-prs',
                'view-all-prs',
                'view-po',
                'view-po-progress',
                'cancel-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ],
            'it-manager' => array_values(array_diff($allPermissions, ['reset-activity-logs'])),
            'it-staff' => array_merge($itMasterReadWrite, [
                'view-dashboard',
                'print-document',
            ]),
            'purchasing-manager' => [
                'approve-prs',
                'view-prs',
                'view-all-prs',
                'assign-canvasser',
                'view-canvassing',
                'approve-po',
                'view-po',
                'view-po-progress',
                'cancel-po',
                'view-products',
                'view-suppliers',
                'view-purchase-history',
                'create-suppliers',
                'update-suppliers',
                'view-procurement-reports',
                'view-supplier-comparison',
                'manage-supplier-comparison',
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
                'view-all-prs',
                'edit-prs',
                'delete-prs',
                'assign-canvasser',
                'approve-prs',
                'view-products',
                'view-suppliers',
                'view-purchase-history',
                'create-suppliers',
                'update-suppliers',
                'view-procurement-reports',
                'view-supplier-comparison',
                'manage-supplier-comparison',
                'view-dashboard',
                'print-document',
            ],
            'im-manager' => array_merge($imProductManage, [
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
                'view-stores-withdrawal',
                'create-stores-withdrawal',
                'update-stores-withdrawal',
                'delete-stores-withdrawal',
                'view-all-stores-withdrawal',
                'view-im-reports',
                'view-po',
                'view-dashboard',
                'print-document',
            ]),
            'im-supervisor' => array_merge($imProductManage, [
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
                'view-stores-withdrawal',
                'create-stores-withdrawal',
                'update-stores-withdrawal',
                'delete-stores-withdrawal',
                'view-all-stores-withdrawal',
                'view-im-reports',
                'view-po',
                'view-dashboard',
                'print-document',
            ]),
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
                'view-stores-withdrawal',
                'create-stores-withdrawal',
                'update-stores-withdrawal',
                'delete-stores-withdrawal',
                'view-all-stores-withdrawal',
                'view-im-reports',
                'view-po',
                'view-dashboard',
                'print-document',
            ],
            'finance-manager' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ]),
            'finance-supervisor' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ]),
            'finance-staff' => array_merge($financeAccountingOps, [
                'view-prs',
                'view-po',
                'view-dashboard',
                'print-document',
            ]),
            'accounting-manager' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ]),
            'accounting-supervisor' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-prs',
                'view-po',
                'view-dashboard',
                'export-report',
                'print-document',
            ]),
            'accounting-staff' => array_merge($financeAccountingOps, [
                'view-prs',
                'view-po',
                'view-dashboard',
                'print-document',
            ]),
            'production-manager' => [
                'create-prs',
                'view-prs',
                'edit-prs',
                'approve-prs',
                'view-po-progress',
                'view-dashboard',
                'print-document',
            ],
            'engineering-manager' => array_merge($imProductCreate, [
                'create-prs',
                'view-prs',
                'edit-prs',
                'approve-prs',
                'view-po-progress',
                'view-dashboard',
                'print-document',
            ]),
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
                'view-employees',
                'manage-employees',
                'view-dashboard',
                'print-document',
            ],
            'hrd-supervisor' => [
                'view-employees',
                'manage-employees',
                'view-dashboard',
                'print-document',
            ],
            'hrd-staff' => [
                'view-employees',
                'manage-employees',
                'view-dashboard',
                'print-document',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo(array_values(array_unique($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
