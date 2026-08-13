<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PermissionModuleGroups
{
    /**
     * Ordered module labels keyed by internal group id.
     *
     * @var array<string, string>
     */
    private const GROUP_LABELS = [
        'users' => 'Users',
        'rbac' => 'Roles & Permissions',
        'employees' => 'Employees',
        'prs' => 'Purchase Requisition',
        'canvassing' => 'Canvassing',
        'po' => 'Purchase Orders',
        'rr' => 'Receiving Reports',
        'transfer' => 'Transfer Slips',
        'delivery' => 'Delivery',
        'stock_adjustment' => 'Stock Adjustments',
        'opening_balance' => 'Opening Balance',
        'stores_withdrawal' => 'Stores Withdrawal',
        'products' => 'Products',
        'suppliers' => 'Suppliers',
        'master_data' => 'Master Data',
        'accounting' => 'Accounting',
        'reports' => 'Reports',
        'general' => 'General',
        'other' => 'Other',
    ];

    /**
     * Longest-match resource suffixes → group id.
     *
     * @var array<string, string>
     */
    private const RESOURCE_GROUPS = [
        'opening-balance-correction' => 'opening_balance',
        'stock-adjustment' => 'stock_adjustment',
        'stores-withdrawal' => 'stores_withdrawal',
        'all-stores-withdrawal' => 'stores_withdrawal',
        'product-categories' => 'master_data',
        'fish-suppliers' => 'master_data',
        'accounting-master' => 'accounting',
        'procurement-reports' => 'reports',
        'accounting-reports' => 'reports',
        'im-reports' => 'reports',
        'supplier-comparison' => 'canvassing',
        'active-sessions' => 'users',
        'activity-logs' => 'users',
        'user-access' => 'users',
        'doc-entries' => 'accounting',
        'exchange-rates' => 'accounting',
        'all-prs' => 'prs',
        'po-progress' => 'po',
        'canvasser' => 'canvassing',
        'canvassing' => 'canvassing',
        'purchase-history' => 'po',
        'products' => 'products',
        'suppliers' => 'suppliers',
        'employees' => 'employees',
        'currencies' => 'master_data',
        'batches' => 'master_data',
        'vessels' => 'master_data',
        'buyers' => 'master_data',
        'users' => 'users',
        'roles' => 'rbac',
        'permissions' => 'rbac',
        'transfer' => 'transfer',
        'delivery' => 'delivery',
        'dashboard' => 'general',
        'report' => 'general',
        'document' => 'general',
        'uom' => 'master_data',
        'fish' => 'master_data',
        'prs' => 'prs',
        'po' => 'po',
        'rr' => 'rr',
    ];

    /**
     * Group permissions by module. Returns ordered list of groups.
     *
     * @param  Collection<int, object>  $permissions
     * @return list<array{key: string, label: string, permissions: Collection<int, object>}>
     */
    public static function group(Collection $permissions): array
    {
        $buckets = [];

        foreach (array_keys(self::GROUP_LABELS) as $key) {
            $buckets[$key] = collect();
        }

        foreach ($permissions->sortBy('name') as $permission) {
            $groupKey = self::resolveGroup((string) $permission->name);
            $buckets[$groupKey]->push($permission);
        }

        $groups = [];

        foreach (self::GROUP_LABELS as $key => $label) {
            if ($buckets[$key]->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => $label,
                'permissions' => $buckets[$key]->values(),
            ];
        }

        return $groups;
    }

    public static function resolveGroup(string $permissionName): string
    {
        $name = strtolower(trim($permissionName));
        $bestMatch = null;
        $bestLength = 0;

        foreach (self::RESOURCE_GROUPS as $resource => $group) {
            if ($name === $resource || str_ends_with($name, '-'.$resource)) {
                $length = strlen($resource);
                if ($length > $bestLength) {
                    $bestLength = $length;
                    $bestMatch = $group;
                }
            }
        }

        return $bestMatch ?? 'other';
    }
}
