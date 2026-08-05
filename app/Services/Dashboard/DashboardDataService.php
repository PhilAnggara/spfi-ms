<?php

namespace App\Services\Dashboard;

use App\Models\Delivery;
use App\Models\Department;
use App\Models\Prs;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\TransferSlip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DashboardDataService
{
    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     subtitle: string,
     *     department_name: string|null,
     *     department_alias: string|null,
     *     month_label: string,
     *     metrics: array<string, int|float>,
     *     charts: array<string, array{labels: array<int, string>, series: array<int, int|float>}>,
     *     lists: array<string, Collection>,
     *     quick_links: array<int, array{label: string, route: string, icon: string}>
     * }
     */
    public function build(User $user, string $key): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $monthLabel = Carbon::parse($monthStart)->translatedFormat('F Y');

        $user->loadMissing('department');

        return match ($key) {
            'admin' => $this->forAdmin($user, $monthStart, $monthEnd, $monthLabel),
            'purchasing' => $this->forPurchasing($user, $monthStart, $monthEnd, $monthLabel),
            'im' => $this->forIm($user, $monthStart, $monthEnd, $monthLabel),
            'finance' => $this->forFinance($user, $monthStart, $monthEnd, $monthLabel),
            'engineering' => $this->forEngineering($user, $monthStart, $monthEnd, $monthLabel),
            'it' => $this->forIt($user, $monthStart, $monthEnd, $monthLabel),
            'md' => $this->forMd($user, $monthStart, $monthEnd, $monthLabel),
            default => $this->forDefault($user, $monthStart, $monthEnd, $monthLabel),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function forAdmin(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'admin',
            title: 'Administrator Dashboard',
            subtitle: 'Full cross-module overview across purchasing, inventory, finance, and system health.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: array_merge(
                $this->purchasingMetrics($monthStart, $monthEnd),
                $this->imMetrics($monthStart, $monthEnd),
                $this->itMetrics(),
            ),
            charts: array_merge(
                $this->purchasingCharts(),
                $this->imCharts($monthStart),
            ),
            lists: [
                'recent_prs' => $this->recentPrs(),
                'recent_po' => $this->recentPurchaseOrders(),
                'recent_rr' => $this->recentReceivingReports(),
                'recent_users' => $this->recentUsers(),
            ],
            quickLinks: [
                ['label' => 'PRS', 'route' => 'prs.index', 'icon' => 'fa-cart-shopping'],
                ['label' => 'Canvassing', 'route' => 'canvassing.index', 'icon' => 'fa-scale-balanced'],
                ['label' => 'Purchase Orders', 'route' => 'purchase-orders.index', 'icon' => 'fa-file-invoice'],
                ['label' => 'Receiving Reports', 'route' => 'receiving-reports.index', 'icon' => 'fa-boxes-stacked'],
                ['label' => 'Stores Withdrawals', 'route' => 'stores-withdrawals.index', 'icon' => 'fa-warehouse'],
                ['label' => 'Transfer Slips', 'route' => 'transfer-slips.index', 'icon' => 'fa-right-left'],
                ['label' => 'Deliveries', 'route' => 'deliveries.index', 'icon' => 'fa-truck'],
                ['label' => 'Users', 'route' => 'user.index', 'icon' => 'fa-users'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forPurchasing(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'purchasing',
            title: 'Purchasing Dashboard',
            subtitle: 'Track PRS intake, canvassing workload, and purchase order pipeline.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $this->purchasingMetrics($monthStart, $monthEnd),
            charts: $this->purchasingCharts(),
            lists: [
                'recent_prs' => $this->recentPrs(),
                'recent_po' => $this->recentPurchaseOrders(),
            ],
            quickLinks: [
                ['label' => 'PRS', 'route' => 'prs.index', 'icon' => 'fa-cart-shopping'],
                ['label' => 'Canvassing', 'route' => 'canvassing.index', 'icon' => 'fa-scale-balanced'],
                ['label' => 'Purchase Orders', 'route' => 'purchase-orders.index', 'icon' => 'fa-file-invoice'],
                ['label' => 'PO Approval', 'route' => 'purchase-orders.approval', 'icon' => 'fa-clipboard-check'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forIm(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'im',
            title: 'Inventory Management Dashboard',
            subtitle: 'Monitor receiving, withdrawals, transfers, and outbound deliveries.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $this->imMetrics($monthStart, $monthEnd),
            charts: $this->imCharts($monthStart),
            lists: [
                'recent_rr' => $this->recentReceivingReports(),
                'recent_ts' => $this->recentTransferSlips(),
                'recent_deliveries' => $this->recentDeliveries(),
            ],
            quickLinks: [
                ['label' => 'Receiving Reports', 'route' => 'receiving-reports.index', 'icon' => 'fa-boxes-stacked'],
                ['label' => 'Stores Withdrawals', 'route' => 'stores-withdrawals.index', 'icon' => 'fa-warehouse'],
                ['label' => 'Transfer Slips', 'route' => 'transfer-slips.index', 'icon' => 'fa-right-left'],
                ['label' => 'Deliveries', 'route' => 'deliveries.index', 'icon' => 'fa-truck'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forFinance(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'finance',
            title: 'Finance Dashboard',
            subtitle: 'Review approved purchase values and receiving activity for the period.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: [
                'po_approved_value_this_month' => $this->poApprovedValue($monthStart, $monthEnd),
                'po_pending_approval' => $this->poPendingApprovalCount(),
                'rr_this_month' => $this->rrThisMonth($monthStart, $monthEnd),
                'prs_this_month' => $this->prsThisMonth($monthStart, $monthEnd),
            ],
            charts: [
                'monthly_po_value' => $this->monthlyPoValueChart(),
                'po_status' => $this->poStatusChart(),
            ],
            lists: [
                'recent_po' => $this->recentPurchaseOrders(),
                'recent_rr' => $this->recentReceivingReports(),
            ],
            quickLinks: [
                ['label' => 'Purchase Orders', 'route' => 'purchase-orders.index', 'icon' => 'fa-file-invoice'],
                ['label' => 'Receiving Reports', 'route' => 'receiving-reports.index', 'icon' => 'fa-boxes-stacked'],
                ['label' => 'Accounting Reports', 'route' => 'accounting.reports.index', 'icon' => 'fa-chart-line'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forEngineering(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $departmentId = $user->department_id;

        return $this->payload(
            key: 'engineering',
            title: 'Engineering Dashboard',
            subtitle: 'Follow Engineering purchase requests from open through completion.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $this->departmentPrsMetrics($departmentId, $monthStart, $monthEnd),
            charts: [
                'prs_status' => $this->prsStatusChart($departmentId),
            ],
            lists: [
                'recent_prs' => $this->recentPrs($departmentId),
            ],
            quickLinks: [
                ['label' => 'My Department PRS', 'route' => 'prs.index', 'icon' => 'fa-cart-shopping'],
                ['label' => 'Create PRS', 'route' => 'prs.create', 'icon' => 'fa-plus'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forIt(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'it',
            title: 'IT Dashboard',
            subtitle: 'System accounts, roles, and department coverage at a glance.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $this->itMetrics(),
            charts: [
                'users_by_department' => $this->usersByDepartmentChart(),
            ],
            lists: [
                'recent_users' => $this->recentUsers(),
            ],
            quickLinks: [
                ['label' => 'Users', 'route' => 'user.index', 'icon' => 'fa-users'],
                ['label' => 'Employees', 'route' => 'employees.index', 'icon' => 'fa-id-card'],
                ['label' => 'Products', 'route' => 'product.index', 'icon' => 'fa-box'],
                ['label' => 'Suppliers', 'route' => 'supplier.index', 'icon' => 'fa-truck-field'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forMd(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        return $this->payload(
            key: 'md',
            title: 'Executive Dashboard',
            subtitle: 'High-level procurement and inventory activity across the organization.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: [
                'prs_this_month' => $this->prsThisMonth($monthStart, $monthEnd),
                'po_approved_value_this_month' => $this->poApprovedValue($monthStart, $monthEnd),
                'rr_this_month' => $this->rrThisMonth($monthStart, $monthEnd),
                'deliveries_this_month' => $this->deliveriesThisMonth($monthStart, $monthEnd),
            ],
            charts: [
                'monthly_prs' => $this->monthlyPrsChart(),
                'prs_status' => $this->prsStatusChart(),
                'po_status' => $this->poStatusChart(),
            ],
            lists: [
                'recent_prs' => $this->recentPrs(),
                'recent_po' => $this->recentPurchaseOrders(),
            ],
            quickLinks: [
                ['label' => 'PRS Approval', 'route' => 'prs.approval.index', 'icon' => 'fa-clipboard-check'],
                ['label' => 'Purchase Orders', 'route' => 'purchase-orders.index', 'icon' => 'fa-file-invoice'],
                ['label' => 'Purchasing Reports', 'route' => 'procurement.reports.index', 'icon' => 'fa-chart-column'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forDefault(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $departmentId = $user->department_id;
        $departmentName = $user->department?->name ?? 'Your department';

        return $this->payload(
            key: 'default',
            title: 'Department Dashboard',
            subtitle: "Purchase request activity for {$departmentName}.",
            user: $user,
            monthLabel: $monthLabel,
            metrics: $this->departmentPrsMetrics($departmentId, $monthStart, $monthEnd),
            charts: [
                'prs_status' => $this->prsStatusChart($departmentId),
            ],
            lists: [
                'recent_prs' => $this->recentPrs($departmentId),
            ],
            quickLinks: [
                ['label' => 'View PRS', 'route' => 'prs.index', 'icon' => 'fa-cart-shopping'],
                ['label' => 'Create PRS', 'route' => 'prs.create', 'icon' => 'fa-plus'],
            ],
        );
    }

    /**
     * @param  array<string, int|float>  $metrics
     * @param  array<string, array{labels: array<int, string>, series: array<int, int|float>}>  $charts
     * @param  array<string, Collection>  $lists
     * @param  array<int, array{label: string, route: string, icon: string}>  $quickLinks
     * @return array<string, mixed>
     */
    private function payload(
        string $key,
        string $title,
        string $subtitle,
        User $user,
        string $monthLabel,
        array $metrics,
        array $charts,
        array $lists,
        array $quickLinks,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'department_name' => $user->department?->name,
            'department_alias' => $user->department?->alias,
            'month_label' => $monthLabel,
            'metrics' => $metrics,
            'charts' => $charts,
            'lists' => $lists,
            'quick_links' => array_values(array_filter(
                $quickLinks,
                fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link['route'])
            )),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function purchasingMetrics(Carbon $monthStart, Carbon $monthEnd): array
    {
        return [
            'prs_this_month' => $this->prsThisMonth($monthStart, $monthEnd),
            'canvass_open' => Prs::query()->where('status', 'CANVASSING')->count(),
            'po_pending_approval' => $this->poPendingApprovalCount(),
            'po_approved_value_this_month' => $this->poApprovedValue($monthStart, $monthEnd),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function imMetrics(Carbon $monthStart, Carbon $monthEnd): array
    {
        return [
            'rr_this_month' => $this->rrThisMonth($monthStart, $monthEnd),
            'sws_open' => (int) DB::table('store_withdrawals')
                ->whereNull('deleted_at')
                ->whereNull('approved_at')
                ->count(),
            'ts_pending' => TransferSlip::query()->whereNull('approved_at')->count(),
            'deliveries_this_month' => $this->deliveriesThisMonth($monthStart, $monthEnd),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function itMetrics(): array
    {
        return [
            'user_accounts' => User::query()->count(),
            'department_count' => Department::query()->count(),
            'role_count' => Role::query()->count(),
            'users_with_department' => User::query()->whereNotNull('department_id')->count(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function departmentPrsMetrics(?int $departmentId, Carbon $monthStart, Carbon $monthEnd): array
    {
        $base = Prs::query()->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        $openStatuses = ['REQUESTED', 'CANVASSING', 'CANVASSER_HOLD', 'ON_HOLD', 'REVISED'];

        return [
            'prs_this_month' => (clone $base)
                ->whereBetween('prs_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'prs_open' => (clone $base)->whereIn('status', $openStatuses)->count(),
            'prs_completed' => (clone $base)->where('status', 'PO_CREATED')->count(),
            'prs_total' => (clone $base)->count(),
        ];
    }

    /**
     * @return array<string, array{labels: array<int, string>, series: array<int, int|float>}>
     */
    private function purchasingCharts(): array
    {
        return [
            'monthly_prs' => $this->monthlyPrsChart(),
            'prs_status' => $this->prsStatusChart(),
            'po_status' => $this->poStatusChart(),
            'top_suppliers' => $this->topSuppliersChart(),
        ];
    }

    /**
     * @return array<string, array{labels: array<int, string>, series: array<int, int|float>}>
     */
    private function imCharts(Carbon $monthStart): array
    {
        return [
            'monthly_rr' => $this->monthlyCountChart(
                ReceivingReport::query(),
                'received_date',
                $monthStart->copy()->startOfMonth()->subMonths(11),
                $monthStart->copy()->endOfMonth(),
            ),
            'monthly_outbound' => $this->monthlyOutboundChart($monthStart),
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function monthlyPrsChart(?int $departmentId = null): array
    {
        $windowStart = now()->copy()->startOfMonth()->subMonths(11);
        $windowEnd = now()->copy()->endOfMonth();
        [$yearExpr, $monthExpr] = $this->yearMonthExpressions('prs_date');

        $query = Prs::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereBetween('prs_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->selectRaw("{$yearExpr} as year_num, {$monthExpr} as month_num, COUNT(*) as total")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}");

        return $this->fillMonthlySeries($query->get(), $windowStart, $windowEnd);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function monthlyCountChart($query, string $dateColumn, Carbon $windowStart, Carbon $windowEnd): array
    {
        [$yearExpr, $monthExpr] = $this->yearMonthExpressions($dateColumn);

        $rows = (clone $query)
            ->whereBetween($dateColumn, [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->selectRaw("{$yearExpr} as year_num, {$monthExpr} as month_num, COUNT(*) as total")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get();

        return $this->fillMonthlySeries($rows, $windowStart, $windowEnd);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function yearMonthExpressions(string $column): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return [
                "CAST(strftime('%Y', {$column}) AS INTEGER)",
                "CAST(strftime('%m', {$column}) AS INTEGER)",
            ];
        }

        return [
            "YEAR({$column})",
            "MONTH({$column})",
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function monthlyOutboundChart(Carbon $monthStart): array
    {
        $windowStart = $monthStart->copy()->startOfMonth()->subMonths(11);
        $windowEnd = $monthStart->copy()->endOfMonth();

        $ts = $this->monthlyCountChart(
            TransferSlip::query(),
            'ts_date',
            $windowStart,
            $windowEnd,
        );
        $dr = $this->monthlyCountChart(
            Delivery::query(),
            'dr_date',
            $windowStart,
            $windowEnd,
        );

        $series = [];
        foreach ($ts['series'] as $index => $tsCount) {
            $series[] = (int) $tsCount + (int) ($dr['series'][$index] ?? 0);
        }

        return [
            'labels' => $ts['labels'],
            'series' => $series,
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, float>}
     */
    private function monthlyPoValueChart(): array
    {
        $windowStart = now()->copy()->startOfMonth()->subMonths(11);
        $windowEnd = now()->copy()->endOfMonth();

        [$yearExpr, $monthExpr] = $this->yearMonthExpressions('approved_at');

        $rows = PurchaseOrder::query()
            ->where('status', 'APPROVED')
            ->whereBetween('approved_at', [$windowStart, $windowEnd])
            ->selectRaw("{$yearExpr} as year_num, {$monthExpr} as month_num, SUM(total) as total")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get();

        $map = $rows->mapWithKeys(function ($row) {
            $key = sprintf('%04d-%02d', (int) $row->year_num, (int) $row->month_num);

            return [$key => (float) $row->total];
        });

        $labels = [];
        $series = [];
        for ($cursor = $windowStart->copy(); $cursor->lte($windowEnd); $cursor->addMonth()) {
            $monthKey = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $series[] = (float) ($map[$monthKey] ?? 0);
        }

        return compact('labels', 'series');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function fillMonthlySeries(Collection $rows, Carbon $windowStart, Carbon $windowEnd): array
    {
        $map = $rows->mapWithKeys(function ($row) {
            $key = sprintf('%04d-%02d', (int) $row->year_num, (int) $row->month_num);

            return [$key => (int) $row->total];
        });

        $labels = [];
        $series = [];
        for ($cursor = $windowStart->copy(); $cursor->lte($windowEnd); $cursor->addMonth()) {
            $monthKey = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $series[] = (int) ($map[$monthKey] ?? 0);
        }

        return compact('labels', 'series');
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function prsStatusChart(?int $departmentId = null): array
    {
        $prsStatusOrder = ['REQUESTED', 'CANVASSING', 'CANVASSER_HOLD', 'ON_HOLD', 'REVISED', 'PO_CREATED'];

        $rows = Prs::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $map = $rows->mapWithKeys(fn ($row) => [strtoupper((string) $row->status) => (int) $row->total]);

        $labels = [];
        $series = [];
        foreach ($prsStatusOrder as $status) {
            $labels[] = str_replace('_', ' ', $status);
            $series[] = (int) ($map[$status] ?? 0);
        }

        foreach ($map->keys()->diff($prsStatusOrder) as $status) {
            $labels[] = str_replace('_', ' ', (string) $status);
            $series[] = (int) $map[$status];
        }

        return compact('labels', 'series');
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function poStatusChart(): array
    {
        $rows = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return [
            'labels' => $rows->map(fn ($row) => str_replace('_', ' ', strtoupper((string) $row->status)))->values()->all(),
            'series' => $rows->map(fn ($row) => (int) $row->total)->values()->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, float>}
     */
    private function topSuppliersChart(): array
    {
        $rows = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->whereNotNull('supplier_id')
            ->selectRaw('supplier_id, SUM(total) as total_value')
            ->groupBy('supplier_id')
            ->orderByDesc('total_value')
            ->limit(7)
            ->get();

        return [
            'labels' => $rows->map(fn ($row) => optional($row->supplier)->name ?: 'Unknown Supplier')->values()->all(),
            'series' => $rows->map(fn ($row) => (float) $row->total_value)->values()->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function usersByDepartmentChart(): array
    {
        $rows = User::query()
            ->selectRaw('department_id, COUNT(*) as total')
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $departments = Department::query()
            ->whereIn('id', $rows->pluck('department_id'))
            ->pluck('alias', 'id');

        return [
            'labels' => $rows->map(fn ($row) => $departments[$row->department_id] ?? 'N/A')->values()->all(),
            'series' => $rows->map(fn ($row) => (int) $row->total)->values()->all(),
        ];
    }

    private function prsThisMonth(Carbon $monthStart, Carbon $monthEnd): int
    {
        return Prs::query()
            ->whereBetween('prs_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
    }

    private function poApprovedValue(Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) PurchaseOrder::query()
            ->where('status', 'APPROVED')
            ->whereBetween('approved_at', [$monthStart, $monthEnd])
            ->sum('total');
    }

    private function poPendingApprovalCount(): int
    {
        return PurchaseOrder::query()->where('status', 'PENDING_APPROVAL')->count();
    }

    private function rrThisMonth(Carbon $monthStart, Carbon $monthEnd): int
    {
        return ReceivingReport::query()
            ->whereBetween('received_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
    }

    private function deliveriesThisMonth(Carbon $monthStart, Carbon $monthEnd): int
    {
        return Delivery::query()
            ->whereBetween('dr_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
    }

    /**
     * @return Collection<int, Prs>
     */
    private function recentPrs(?int $departmentId = null): Collection
    {
        return Prs::query()
            ->with('department:id,name,alias')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->latest('id')
            ->limit(5)
            ->get(['id', 'prs_number', 'status', 'prs_date', 'department_id']);
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    private function recentPurchaseOrders(): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'po_number', 'status', 'total', 'supplier_id', 'created_at']);
    }

    /**
     * @return Collection<int, ReceivingReport>
     */
    private function recentReceivingReports(): Collection
    {
        return ReceivingReport::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'rr_number', 'received_date', 'purchase_order_id']);
    }

    /**
     * @return Collection<int, TransferSlip>
     */
    private function recentTransferSlips(): Collection
    {
        return TransferSlip::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'ts_number', 'ts_date', 'approved_at']);
    }

    /**
     * @return Collection<int, Delivery>
     */
    private function recentDeliveries(): Collection
    {
        return Delivery::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'dr_number', 'dr_date', 'to_location']);
    }

    /**
     * @return Collection<int, User>
     */
    private function recentUsers(): Collection
    {
        return User::query()
            ->with('department:id,name,alias')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'username', 'email', 'department_id', 'created_at']);
    }
}
