<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserActivityLog extends Model
{
    public const ACTION_LOGIN = 'login';

    public const ACTION_LOGOUT = 'logout';

    public const ACTION_FORCE_LOGOUT = 'force_logout';

    public const ACTION_ACTIVE = 'active';

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_HELD = 'held';

    public const ACTION_REASSIGNED = 'reassigned';

    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_WITHDRAWN = 'withdrawn';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_REQUESTED_CHANGES = 'requested_changes';

    /**
     * @var array<string, string>
     */
    private const ROUTE_PAGE_LABELS = [
        'dashboard' => 'Dashboard',
        'profile.edit' => 'Profile',
        'notifications.index' => 'Notifications',
        'notifications.recent' => 'Notifications refresh',
        'notifications.unread-count' => 'Notifications refresh',
        'user.index' => 'Manage Users',
        'active-sessions.index' => 'Active Users / Sessions',
        'active-sessions.show' => 'Active Users / Sessions',
        'employees.index' => 'Employees',
        'product.index' => 'Products',
        'product-category.index' => 'Product Categories',
        'unit-of-measurement.index' => 'Units of Measure',
        'supplier.index' => 'Suppliers',
        'buyer.index' => 'Buyers',
        'currency.index' => 'Currencies',
        'batch.index' => 'Batch Numbers',
        'fish-supplier.index' => 'Fish Suppliers',
        'vessel.index' => 'Vessels',
        'fish.index' => 'Fish',
        'prs.index' => 'Purchase Requisitions',
        'prs.create' => 'Create PRS',
        'prs.edit' => 'Edit PRS',
        'prs.approval.index' => 'PRS Approval',
        'prs.approval.show' => 'PRS Approval Detail',
        'canvassing.index' => 'Canvassing',
        'canvassing.show' => 'Canvassing Detail',
        'purchase-orders.index' => 'Purchase Orders',
        'purchase-orders.draft' => 'PO Draft',
        'purchase-orders.approval' => 'PO Approval',
        'purchase-orders.approve' => 'PO Approval',
        'purchase-orders.request-changes' => 'PO Approval',
        'purchase-orders.submit' => 'Purchase Orders',
        'purchase-orders.withdraw' => 'Purchase Orders',
        'purchase-orders.cancel' => 'Purchase Orders',
        'purchase-orders.show' => 'Purchase Order Detail',
        'prs.approve' => 'PRS Approval',
        'prs.reject' => 'PRS Approval',
        'prs.hold' => 'PRS Approval',
        'prs.reassign' => 'PRS Approval',
        'procurement.supplier-comparison.index' => 'Supplier Comparison',
        'procurement.reports.index' => 'Purchasing Reports',
        'receiving-reports.index' => 'Receiving Reports',
        'stores-withdrawals.index' => 'Stores Withdrawals',
        'stores-withdrawals.create' => 'Create Stores Withdrawal',
        'transfer-slips.index' => 'Transfer Slips',
        'deliveries.index' => 'Deliveries',
        'im.reports.index' => 'IM Reports',
        'accounting.reports.index' => 'Accounting Reports',
        'accounting.exchange-rates.index' => 'Exchange Rates',
        'accounting.doc-entries.index' => 'Document Entries',
        'accounting.groupings.index' => 'Accounting Groupings',
        'accounting.group-codes.index' => 'Accounting Group Codes',
        'accounting.codes.index' => 'Accounting Codes',
        'accounting.balance-sheet.index' => 'Balance Sheet Mapping',
    ];

    /**
     * @var array<string, string>
     */
    private const PATH_PAGE_LABELS = [
        '/notifications/recent' => 'Notifications refresh',
        '/notifications/unread-count' => 'Notifications refresh',
        '/' => 'Dashboard',
    ];

    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'ip_address',
        'user_agent',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'actor_id' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return match ($this->action) {
            self::ACTION_LOGIN => 'Logged in',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_FORCE_LOGOUT => 'Force logged out',
            self::ACTION_ACTIVE => 'Visited page',
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_HELD => 'Held',
            self::ACTION_REASSIGNED => 'Reassigned',
            self::ACTION_SUBMITTED => 'Submitted',
            self::ACTION_WITHDRAWN => 'Withdrawn',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_REQUESTED_CHANGES => 'Requested changes',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    public function pageLabel(): ?string
    {
        if (! empty($this->meta['page'])) {
            return (string) $this->meta['page'];
        }

        return self::labelForRoute(
            $this->meta['route'] ?? null,
            $this->meta['path'] ?? null,
        );
    }

    public function subjectLabel(): ?string
    {
        if (! empty($this->meta['subject'])) {
            return (string) $this->meta['subject'];
        }

        if (isset($this->meta['subject_id']) && $this->meta['subject_id'] !== '') {
            return '#'.$this->meta['subject_id'];
        }

        return null;
    }

    public static function labelForRoute(?string $route, ?string $path = null): ?string
    {
        if ($route !== null && $route !== '') {
            if (isset(self::ROUTE_PAGE_LABELS[$route])) {
                return self::ROUTE_PAGE_LABELS[$route];
            }

            foreach (self::ROUTE_PAGE_LABELS as $knownRoute => $label) {
                if (str_starts_with($route, $knownRoute)) {
                    return $label;
                }
            }

            $segments = explode('.', $route);
            $resourceSegments = array_slice($segments, 0, -1);
            $action = $segments[array_key_last($segments)] ?? null;

            if ($resourceSegments !== []) {
                $approvalRoute = implode('.', $resourceSegments).'.approval';
                if (in_array($action, ['approve', 'reject', 'hold', 'reassign', 'request-changes'], true)
                    && isset(self::ROUTE_PAGE_LABELS[$approvalRoute])) {
                    return self::ROUTE_PAGE_LABELS[$approvalRoute];
                }

                $indexRoute = implode('.', $resourceSegments).'.index';
                if (in_array($action, ['store', 'update', 'destroy', 'submit', 'withdraw', 'cancel'], true)
                    && isset(self::ROUTE_PAGE_LABELS[$indexRoute])) {
                    return self::ROUTE_PAGE_LABELS[$indexRoute];
                }
            }

            $resource = str_replace(['-', '_'], ' ', $segments[0] ?? $route);
            $base = Str::title($resource);

            return match ($action) {
                'index', null => $base,
                'create' => 'Create '.$base,
                'edit' => 'Edit '.$base,
                'show' => $base.' Detail',
                'store', 'update', 'destroy' => $base,
                'datatables' => $base.' table data',
                default => $base.' · '.Str::title(str_replace(['-', '_'], ' ', (string) $action)),
            };
        }

        if ($path !== null && $path !== '') {
            $normalized = '/'.ltrim($path, '/');

            if (isset(self::PATH_PAGE_LABELS[$normalized])) {
                return self::PATH_PAGE_LABELS[$normalized];
            }

            $parts = array_values(array_filter(explode('/', trim($normalized, '/'))));

            if ($parts === []) {
                return 'Dashboard';
            }

            return Str::title(str_replace(['-', '_'], ' ', $parts[0]));
        }

        return null;
    }
}
