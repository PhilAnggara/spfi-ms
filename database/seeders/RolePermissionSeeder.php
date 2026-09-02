<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles that received leftover approve-prs but should not assign canvassers.
     *
     * @var list<string>
     */
    private const APPROVE_PRS_EXCLUDE_ROLES = [
        'production-manager',
        'engineering-manager',
    ];

    /**
     * Run the database seeds.
     *
     * Assignments are additive (givePermissionTo) so re-seeding does not revoke
     * permissions granted through the Role & Permission management UI.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->migrateLegacyPermissionNames();

        $permissionsUsers = [
            'create-users',
            'view-users',
            'update-users',
            'delete-users',
            'view-active-sessions',
            'force-logout-users',
            'reset-activity-logs',
            'assign-user-access',
        ];

        $permissionsPrs = [
            'view-all-prs',
            'update-department-prs',
            'update-all-prs',
            'delete-department-prs',
            'delete-all-prs',
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
            'update-all-po',
        ];

        $permissionsRr = [
            'create-rr',
            'view-rr',
            'update-rr',
            'delete-rr',
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
            'view-all-stores-withdrawal',
            'update-department-stores-withdrawal',
            'update-all-stores-withdrawal',
            'delete-department-stores-withdrawal',
            'delete-all-stores-withdrawal',
        ];

        $permissionsMaster = array_merge(
            [
                'view-products',
                'create-products',
                'update-products',
                'delete-products',
                'view-suppliers',
                'create-suppliers',
                'update-suppliers',
                'delete-suppliers',
                'view-purchase-history',
                'view-print-calibration',
                'manage-print-calibration',
            ],
            $this->crud('product-categories'),
            $this->crud('uom'),
            $this->crud('buyers'),
            $this->crud('currencies'),
            $this->crud('batches'),
            $this->crud('fish-suppliers'),
            $this->crud('vessels'),
            $this->crud('fish'),
            $this->crud('employees'),
            $this->crud('accounting-master'),
        );

        $permissionsReportsOps = [
            'view-procurement-reports',
            'view-im-reports',
            'view-accounting-reports',
            'view-doc-entries',
            'update-doc-entries',
            'view-accounting-inventory',
            'encode-accounting-inventory',
            'create-accounting-inventory',
            'void-accounting-inventory',
            'view-exchange-rates',
            'create-exchange-rates',
            'view-supplier-comparison',
            'select-supplier-comparison',
        ];

        $permissionsRbac = [
            'view-roles',
            'create-roles',
            'update-roles',
            'delete-roles',
            'view-permissions',
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
        )));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->deleteObsoletePermissions([
            'create-prs',
            'view-prs',
            'edit-prs',
            'update-prs',
            'delete-prs',
            'view-stores-withdrawal',
            'create-stores-withdrawal',
            'update-stores-withdrawal',
            'delete-stores-withdrawal',
            'view-dashboard',
            'export-report',
            'print-document',
        ]);

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

        $itMasterReadWrite = array_merge(
            [
                'view-products',
                'create-products',
                'update-products',
                'delete-products',
                'view-suppliers',
                'create-suppliers',
                'update-suppliers',
                'delete-suppliers',
                'view-purchase-history',
                'view-users',
                'create-users',
                'update-users',
                'delete-users',
                'view-active-sessions',
                'force-logout-users',
            ],
            $this->crud('product-categories'),
            $this->crud('uom'),
            $this->crud('buyers'),
            $this->crud('currencies'),
            $this->crud('batches'),
            $this->crud('fish-suppliers'),
            $this->crud('vessels'),
            $this->crud('fish'),
            $this->crud('accounting-master'),
            $this->crud('employees'),
            [
                'view-print-calibration',
                'manage-print-calibration',
            ],
        );

        $imProductCreate = ['view-products', 'create-products'];
        $imProductManage = ['view-products', 'create-products', 'update-products', 'delete-products'];

        $financeAccountingOps = [
            'view-accounting-reports',
            'view-doc-entries',
            'update-doc-entries',
            'view-accounting-inventory',
            'encode-accounting-inventory',
            'create-accounting-inventory',
            'void-accounting-inventory',
            'view-exchange-rates',
        ];

        $imWarehouseOps = [
            'create-rr',
            'view-rr',
            'update-rr',
            'delete-rr',
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
            'view-all-stores-withdrawal',
            'update-all-stores-withdrawal',
            'delete-all-stores-withdrawal',
            'view-im-reports',
            'view-po',
        ];

        $rolePermissions = [
            'administrator' => $allPermissions,
            'general-manager' => [
                'view-all-prs',
                'view-po',
                'view-po-progress',
                'cancel-po',
            ],
            'it-manager' => array_values(array_diff($allPermissions, ['reset-activity-logs'])),
            'it-staff' => $itMasterReadWrite,
            'purchasing-manager' => [
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
                'select-supplier-comparison',
            ],
            'purchasing-staff' => [
                'view-canvassing',
                'update-canvassing',
                'create-po',
                'view-po',
                'submit-po',
                'cancel-po',
                'view-po-progress',
                'view-all-prs',
                'assign-canvasser',
                'view-products',
                'view-suppliers',
                'view-purchase-history',
                'create-suppliers',
                'update-suppliers',
                'view-procurement-reports',
                'view-supplier-comparison',
                'select-supplier-comparison',
            ],
            'im-manager' => array_merge($imProductManage, $imWarehouseOps),
            'im-supervisor' => array_merge($imProductManage, $imWarehouseOps),
            'im-staff' => $imWarehouseOps,
            'finance-manager' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-po',
            ]),
            'finance-supervisor' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-po',
            ]),
            'finance-staff' => array_merge($financeAccountingOps, [
                'view-po',
            ]),
            'accounting-manager' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-po',
            ]),
            'accounting-supervisor' => array_merge($financeAccountingOps, [
                'create-exchange-rates',
                'view-po',
            ]),
            'accounting-staff' => array_merge($financeAccountingOps, [
                'view-po',
            ]),
            'production-manager' => [
                'view-po-progress',
            ],
            'engineering-manager' => array_merge($imProductCreate, [
                'view-po-progress',
            ]),
            'engineering-supervisor' => [
                'view-po',
            ],
            'engineering-staff' => [
                'view-po',
            ],
            'hrd-manager' => $this->crud('employees'),
            'hrd-supervisor' => $this->crud('employees'),
            'hrd-staff' => $this->crud('employees'),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo(array_values(array_unique($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function crud(string $resource): array
    {
        return [
            "view-{$resource}",
            "create-{$resource}",
            "update-{$resource}",
            "delete-{$resource}",
        ];
    }

    private function migrateLegacyPermissionNames(): void
    {
        $this->renamePermission('edit-users', 'update-users');
        $this->renamePermission('manage-user-access', 'assign-user-access');
        $this->renamePermission('manage-supplier-comparison', 'select-supplier-comparison');

        $this->mergePermission('approve-prs', 'assign-canvasser', self::APPROVE_PRS_EXCLUDE_ROLES);

        $this->splitPermission('manage-active-sessions', ['view-active-sessions', 'force-logout-users']);
        $this->splitPermission('manage-roles', ['view-roles', 'create-roles', 'update-roles', 'delete-roles']);
        $this->splitPermission('manage-permissions', ['view-permissions']);

        foreach ([
            'product-categories',
            'uom',
            'buyers',
            'currencies',
            'batches',
            'fish-suppliers',
            'vessels',
            'fish',
            'employees',
            'accounting-master',
        ] as $resource) {
            $this->splitPermission("manage-{$resource}", [
                "create-{$resource}",
                "update-{$resource}",
                "delete-{$resource}",
            ]);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function deleteObsoletePermissions(array $names): void
    {
        foreach ($names as $name) {
            $permission = $this->findPermission($name);
            if ($permission !== null) {
                $this->deletePermission($permission);
            }
        }
    }

    private function renamePermission(string $from, string $to): void
    {
        $fromPermission = $this->findPermission($from);
        $toPermission = $this->findPermission($to);

        if ($fromPermission === null) {
            return;
        }

        if ($toPermission === null) {
            $fromPermission->name = $to;
            $fromPermission->save();

            return;
        }

        $this->copyAssignments($fromPermission, $toPermission);
        $this->deletePermission($fromPermission);
    }

    /**
     * @param  list<string>  $excludeRoleNames
     */
    private function mergePermission(string $from, string $to, array $excludeRoleNames = []): void
    {
        $fromPermission = $this->findPermission($from);

        if ($fromPermission === null) {
            return;
        }

        $toPermission = Permission::firstOrCreate(['name' => $to, 'guard_name' => 'web']);
        $this->copyAssignments($fromPermission, $toPermission, $excludeRoleNames);
        $this->deletePermission($fromPermission);
    }

    /**
     * @param  list<string>  $targets
     */
    private function splitPermission(string $from, array $targets): void
    {
        $fromPermission = $this->findPermission($from);

        if ($fromPermission === null) {
            return;
        }

        foreach ($targets as $target) {
            $targetPermission = Permission::firstOrCreate(['name' => $target, 'guard_name' => 'web']);
            $this->copyAssignments($fromPermission, $targetPermission);
        }

        $this->deletePermission($fromPermission);
    }

    /**
     * @param  list<string>  $excludeRoleNames
     */
    private function copyAssignments(Permission $from, Permission $to, array $excludeRoleNames = []): void
    {
        $from->load(['roles', 'users']);

        foreach ($from->roles as $role) {
            if (in_array($role->name, $excludeRoleNames, true)) {
                continue;
            }

            $role->givePermissionTo($to);
        }

        foreach ($from->users as $user) {
            $user->givePermissionTo($to);
        }
    }

    private function deletePermission(Permission $permission): void
    {
        $permission->roles()->detach();
        $permission->users()->detach();
        $permission->delete();
    }

    private function findPermission(string $name): ?Permission
    {
        return Permission::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();
    }
}
