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

    public const ACTION_TYPING = 'typing';

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
        'chat.typing' => 'Chat',
        'chat.messages.index' => 'Chat',
        'chat.messages.store' => 'Chat',
        'chat.direct-messages.store' => 'Chat',
        'chat.conversations.store' => 'Chat',
        'chat.conversations.index' => 'Chat',
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
            self::ACTION_TYPING => 'Typing',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Short English one-liner for Active Sessions UI.
     */
    public function summary(): string
    {
        $subject = $this->subjectLabel();
        $page = $this->pageLabel();
        $route = (string) ($this->meta['route'] ?? '');

        return match ($this->action) {
            self::ACTION_LOGIN => 'Logged in',
            self::ACTION_LOGOUT => 'Logged out',
            self::ACTION_FORCE_LOGOUT => 'Force logged out',
            self::ACTION_TYPING => $subject !== null ? 'Typing to '.$subject : 'Typing',
            self::ACTION_ACTIVE => $this->activeSummary($route, $page, $subject),
            self::ACTION_CREATED => $this->createdSummary($route, $page, $subject),
            self::ACTION_UPDATED => $this->resourceSummary('Edited', $page, $subject),
            self::ACTION_DELETED => $this->resourceSummary('Deleted', $page, $subject),
            self::ACTION_APPROVED => $this->resourceSummary('Approved', $page, $subject),
            self::ACTION_REJECTED => $this->resourceSummary('Rejected', $page, $subject),
            self::ACTION_HELD => $this->resourceSummary('Held', $page, $subject),
            self::ACTION_REASSIGNED => $this->resourceSummary('Reassigned', $page, $subject),
            self::ACTION_SUBMITTED => $this->resourceSummary('Submitted', $page, $subject),
            self::ACTION_WITHDRAWN => $this->resourceSummary('Withdrawn', $page, $subject),
            self::ACTION_CANCELLED => $this->resourceSummary('Cancelled', $page, $subject),
            self::ACTION_REQUESTED_CHANGES => $this->resourceSummary('Requested changes on', $page, $subject),
            default => $this->resourceSummary($this->label(), $page, $subject),
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
            $id = '#'.$this->meta['subject_id'];
            $code = trim((string) ($this->meta['subject_code'] ?? ''));

            return $code !== '' ? $id.' ('.$code.')' : $id;
        }

        return null;
    }

    /**
     * Secondary detail line for the activity timeline (page / method / path / notes).
     */
    public function detailLabel(): ?string
    {
        $parts = [];

        $page = $this->pageLabel();
        if ($page !== null && $page !== '') {
            $parts[] = $page;
        }

        $method = strtoupper(trim((string) ($this->meta['method'] ?? '')));
        if ($method !== '') {
            $parts[] = $method;
        }

        $path = trim((string) ($this->meta['path'] ?? ''));
        if ($path !== '') {
            $parts[] = $path;
        }

        if ($this->action === self::ACTION_FORCE_LOGOUT && $this->actor) {
            $parts[] = 'by '.$this->actor->name;
        }

        $message = trim((string) ($this->meta['message'] ?? ''));
        if ($message !== '') {
            $parts[] = $message;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
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

    private function activeSummary(string $route, ?string $page, ?string $subject): string
    {
        if ($route === 'chat.messages.index') {
            return $subject !== null ? 'Opened chat with '.$subject : 'Opened chat';
        }

        return $page !== null ? 'Visited '.$page : 'Visited page';
    }

    private function createdSummary(string $route, ?string $page, ?string $subject): string
    {
        if (in_array($route, ['chat.messages.store', 'chat.direct-messages.store'], true)) {
            return $subject !== null ? 'Sent chat to '.$subject : 'Sent chat';
        }

        if ($route === 'chat.conversations.store') {
            return $subject !== null ? 'Started chat with '.$subject : 'Started chat';
        }

        return $this->resourceSummary('Created', $page, $subject);
    }

    private function resourceSummary(string $verb, ?string $page, ?string $subject): string
    {
        $resource = $this->singularResourceLabel($page);

        if ($resource !== null && $subject !== null) {
            return $verb.' '.$resource.' '.$subject;
        }

        if ($resource !== null) {
            return $verb.' '.$resource;
        }

        if ($subject !== null) {
            return $verb.' '.$subject;
        }

        return $verb;
    }

    private function singularResourceLabel(?string $page): ?string
    {
        if ($page === null || $page === '') {
            return null;
        }

        $normalized = strtolower(trim($page));

        return match ($normalized) {
            'products', 'product' => 'product',
            'product categories', 'product category' => 'product category',
            'purchase requisitions', 'prs' => 'PRS',
            'purchase orders', 'purchase order detail', 'po draft', 'po approval' => 'purchase order',
            'transfer slips' => 'transfer slip',
            'stores withdrawals', 'create stores withdrawal' => 'stores withdrawal',
            'receiving reports' => 'receiving report',
            'deliveries' => 'delivery',
            'suppliers' => 'supplier',
            'buyers' => 'buyer',
            'currencies' => 'currency',
            'employees' => 'employee',
            'manage users' => 'user',
            'chat' => 'chat',
            'screen messages' => 'screen message',
            default => Str::lower(Str::singular($page)),
        };
    }
}
