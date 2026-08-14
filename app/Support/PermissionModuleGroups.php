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
     * Permissions that are not CRUD view/create/update/delete of their suffix.
     *
     * @var array<string, string>
     */
    private const OTHER_RESOURCES = [
        'view-all-prs' => 'prs',
        'update-department-prs' => 'prs',
        'update-all-prs' => 'prs',
        'delete-department-prs' => 'prs',
        'delete-all-prs' => 'prs',
        'view-all-stores-withdrawal' => 'stores-withdrawal',
        'update-department-stores-withdrawal' => 'stores-withdrawal',
        'update-all-stores-withdrawal' => 'stores-withdrawal',
        'delete-department-stores-withdrawal' => 'stores-withdrawal',
        'delete-all-stores-withdrawal' => 'stores-withdrawal',
        'view-po-progress' => 'po',
        'view-purchase-history' => 'po',
        'update-all-po' => 'po',
        'assign-canvasser' => 'canvasser',
        'assign-user-access' => 'users',
        'force-logout-users' => 'active-sessions',
        'reset-activity-logs' => 'activity-logs',
        'select-supplier-comparison' => 'supplier-comparison',
        'approve-po' => 'po',
        'submit-po' => 'po',
        'cancel-po' => 'po',
    ];

    /**
     * @var array<string, string>
     */
    private const RESOURCE_LABELS = [
        'uom' => 'UOM',
        'prs' => 'PRS',
        'po' => 'PO',
        'rr' => 'RR',
        'accounting-master' => 'Accounting master',
        'active-sessions' => 'Active sessions',
        'activity-logs' => 'Activity logs',
        'product-categories' => 'Product categories',
        'fish-suppliers' => 'Fish suppliers',
        'stores-withdrawal' => 'Stores withdrawal',
        'stock-adjustment' => 'Stock adjustments',
        'opening-balance-correction' => 'Opening balance',
        'supplier-comparison' => 'Supplier comparison',
        'doc-entries' => 'Document entries',
        'exchange-rates' => 'Exchange rates',
        'procurement-reports' => 'Procurement reports',
        'accounting-reports' => 'Accounting reports',
        'im-reports' => 'IM reports',
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

    /**
     * Group permissions into module tables with CRUD columns plus Other.
     *
     * @param  Collection<int, object>  $permissions
     * @return list<array{key: string, label: string, rows: list<array{resource: string, label: string, view: ?object, create: ?object, update: ?object, delete: ?object, other: list<object>}>}>
     */
    public static function matrix(Collection $permissions): array
    {
        $grouped = self::group($permissions);
        $matrix = [];

        foreach ($grouped as $group) {
            /** @var array<string, array{resource: string, label: string, view: ?object, create: ?object, update: ?object, delete: ?object, other: list<object>}> $rows */
            $rows = [];

            foreach ($group['permissions'] as $permission) {
                $parsed = self::parse((string) $permission->name);
                $resource = $parsed['resource'];

                if (! isset($rows[$resource])) {
                    $rows[$resource] = [
                        'resource' => $resource,
                        'label' => self::resourceLabel($resource),
                        'view' => null,
                        'create' => null,
                        'update' => null,
                        'delete' => null,
                        'other' => [],
                    ];
                }

                if (in_array($parsed['column'], ['view', 'create', 'update', 'delete'], true)) {
                    $rows[$resource][$parsed['column']] = $permission;
                } else {
                    $rows[$resource]['other'][] = $permission;
                }
            }

            $matrix[] = [
                'key' => $group['key'],
                'label' => $group['label'],
                'rows' => array_values($rows),
            ];
        }

        return $matrix;
    }

    /**
     * @return array{column: string, resource: string, verb: string}
     */
    public static function parse(string $permissionName): array
    {
        $name = strtolower(trim($permissionName));

        if (isset(self::OTHER_RESOURCES[$name])) {
            $resource = self::OTHER_RESOURCES[$name];

            return [
                'column' => 'other',
                'resource' => $resource,
                'verb' => self::actionLabel($name, $resource),
            ];
        }

        if (preg_match('/^(view|create|update|delete)-(.+)$/', $name, $matches) === 1) {
            return [
                'column' => $matches[1],
                'resource' => $matches[2],
                'verb' => $matches[1],
            ];
        }

        $parts = explode('-', $name, 2);

        return [
            'column' => 'other',
            'resource' => $parts[1] ?? $name,
            'verb' => str_replace('-', ' ', $parts[0]),
        ];
    }

    public static function resourceLabel(string $resource): string
    {
        if (isset(self::RESOURCE_LABELS[$resource])) {
            return self::RESOURCE_LABELS[$resource];
        }

        return ucwords(str_replace('-', ' ', $resource));
    }

    public static function actionLabel(string $permissionName, string $resource): string
    {
        $name = strtolower(trim($permissionName));
        $suffix = '-'.$resource;

        if (str_ends_with($name, $suffix)) {
            return str_replace('-', ' ', substr($name, 0, -strlen($suffix)));
        }

        return str_replace('-', ' ', $name);
    }
}
