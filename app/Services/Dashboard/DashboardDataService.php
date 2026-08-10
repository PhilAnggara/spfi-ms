<?php

namespace App\Services\Dashboard;

use App\Models\Delivery;
use App\Models\Department;
use App\Models\Prs;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\Session;
use App\Models\TransferSlip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DashboardDataService
{
    private const CACHE_TTL_SECONDS = 600;

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
     *     lists: array<string, Collection>
     * }
     */
    public function build(User $user, string $key): array
    {
        $user->loadMissing('department');

        $cacheKey = sprintf(
            'dashboard.v1.%s.%d.%s',
            $key,
            $user->id,
            $user->department_id ?? 'none',
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($user, $key): array {
            $now = now();
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();
            $monthLabel = Carbon::parse($monthStart)->translatedFormat('F Y');

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
        });
    }

    /**
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>}>}
     */
    public function openPrsHeatmapCached(): array
    {
        return Cache::remember(
            'dashboard.heatmap.open_prs',
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->openPrsHeatmapChart(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forAdmin(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $activeUsers = $this->activeUsersSnapshot();

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
                [
                    'doc_entry_pending' => $this->docEntryPendingCount(),
                    'users_online' => $activeUsers['online_count'],
                    'active_sessions' => $activeUsers['session_count'],
                ],
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
                'active_users' => $activeUsers['items'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forPurchasing(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $merged = $this->mergeDepartmentBaseline($user, $monthStart, $monthEnd, [
            'metrics' => $this->purchasingMetrics($monthStart, $monthEnd),
            'charts' => $this->purchasingCharts(),
            'lists' => [
                'recent_prs' => $this->recentPrs(),
                'recent_po' => $this->recentPurchaseOrders(),
            ],
        ]);

        return $this->payload(
            key: 'purchasing',
            title: 'Purchasing Dashboard',
            subtitle: 'Track PRS intake, canvassing workload, and purchase order pipeline.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $merged['metrics'],
            charts: $merged['charts'],
            lists: $merged['lists'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forIm(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $merged = $this->mergeDepartmentBaseline($user, $monthStart, $monthEnd, [
            'metrics' => $this->imMetrics($monthStart, $monthEnd),
            'charts' => $this->imCharts($monthStart),
            'lists' => [
                'recent_rr' => $this->recentReceivingReports(),
                'recent_ts' => $this->recentTransferSlips(),
                'recent_deliveries' => $this->recentDeliveries(),
            ],
        ]);

        return $this->payload(
            key: 'im',
            title: 'Inventory Management Dashboard',
            subtitle: 'Monitor receiving, withdrawals, transfers, and outbound deliveries.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $merged['metrics'],
            charts: $merged['charts'],
            lists: $merged['lists'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forFinance(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $merged = $this->mergeDepartmentBaseline($user, $monthStart, $monthEnd, [
            'metrics' => [
                'po_approved_value_this_month' => $this->poApprovedValue($monthStart, $monthEnd),
                'po_pending_approval' => $this->poPendingApprovalCount(),
                'rr_this_month' => $this->rrThisMonth($monthStart, $monthEnd),
                'prs_this_month' => $this->prsThisMonth($monthStart, $monthEnd),
                'doc_entry_pending' => $this->docEntryPendingCount(),
            ],
            'charts' => [
                'monthly_po_value' => $this->monthlyPoValueChart(),
                'po_status' => $this->poStatusChart(),
            ],
            'lists' => [
                'recent_po' => $this->recentPurchaseOrders(),
                'recent_rr' => $this->recentReceivingReports(),
            ],
        ]);

        return $this->payload(
            key: 'finance',
            title: 'Finance Dashboard',
            subtitle: 'Review approved purchase values and receiving activity for the period.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $merged['metrics'],
            charts: $merged['charts'],
            lists: $merged['lists'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forEngineering(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $merged = $this->mergeDepartmentBaseline($user, $monthStart, $monthEnd, [
            'metrics' => [],
            'charts' => [],
            'lists' => [],
        ]);

        return $this->payload(
            key: 'engineering',
            title: 'Engineering Dashboard',
            subtitle: 'Follow Engineering purchase requests and stores withdrawals.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $merged['metrics'],
            charts: $merged['charts'],
            lists: $merged['lists'],
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
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forMd(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $merged = $this->mergeDepartmentBaseline($user, $monthStart, $monthEnd, [
            'metrics' => [
                'prs_this_month' => $this->prsThisMonth($monthStart, $monthEnd),
                'po_approved_value_this_month' => $this->poApprovedValue($monthStart, $monthEnd),
                'rr_this_month' => $this->rrThisMonth($monthStart, $monthEnd),
                'deliveries_this_month' => $this->deliveriesThisMonth($monthStart, $monthEnd),
                'canvass_open' => Prs::query()->where('status', 'CANVASSING')->count(),
                'po_pending_approval' => $this->poPendingApprovalCount(),
                'sws_open' => $this->swsOpenCount(),
            ],
            'charts' => [
                'monthly_prs' => $this->monthlyPrsChart(),
                'prs_status' => $this->prsStatusChart(),
                'po_status' => $this->poStatusChart(),
            ],
            'lists' => [
                'recent_prs' => $this->recentPrs(),
                'recent_po' => $this->recentPurchaseOrders(),
            ],
        ]);

        return $this->payload(
            key: 'md',
            title: 'Executive Dashboard',
            subtitle: 'High-level procurement and inventory activity across the organization.',
            user: $user,
            monthLabel: $monthLabel,
            metrics: $merged['metrics'],
            charts: $merged['charts'],
            lists: $merged['lists'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forDefault(User $user, Carbon $monthStart, Carbon $monthEnd, string $monthLabel): array
    {
        $departmentName = $user->department?->name ?? 'Your department';
        $baseline = $this->departmentBaseline($user->department_id, $monthStart, $monthEnd);

        return $this->payload(
            key: 'default',
            title: 'Department Dashboard',
            subtitle: "Purchase requests and stores withdrawals for {$departmentName}.",
            user: $user,
            monthLabel: $monthLabel,
            metrics: $baseline['metrics'],
            charts: $baseline['charts'],
            lists: $baseline['lists'],
        );
    }

    /**
     * @param  array<string, int|float>  $metrics
     * @param  array<string, array{labels: array<int, string>, series: array<int, int|float>}>  $charts
     * @param  array<string, Collection>  $lists
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
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function purchasingMetrics(Carbon $monthStart, Carbon $monthEnd): array
    {
        $monthStartDate = $monthStart->toDateString();
        $monthEndDate = $monthEnd->toDateString();

        $prs = Prs::query()
            ->selectRaw(
                'SUM(CASE WHEN prs_date >= ? AND prs_date <= ? THEN 1 ELSE 0 END) as prs_this_month',
                [$monthStartDate, $monthEndDate],
            )
            ->selectRaw("SUM(CASE WHEN status IN ('REQUESTED', 'REVISED') THEN 1 ELSE 0 END) as prs_to_assign")
            ->selectRaw("SUM(CASE WHEN status = 'CANVASSING' THEN 1 ELSE 0 END) as canvass_open")
            ->first();

        $po = PurchaseOrder::query()
            ->selectRaw("SUM(CASE WHEN status = 'PENDING_APPROVAL' THEN 1 ELSE 0 END) as po_pending_approval")
            ->selectRaw("SUM(CASE WHEN status = 'CHANGES_REQUESTED' THEN 1 ELSE 0 END) as po_changes_requested")
            ->first();

        return [
            'prs_this_month' => (int) ($prs->prs_this_month ?? 0),
            'prs_to_assign' => (int) ($prs->prs_to_assign ?? 0),
            'canvass_open' => (int) ($prs->canvass_open ?? 0),
            'po_pending_approval' => (int) ($po->po_pending_approval ?? 0),
            'po_changes_requested' => (int) ($po->po_changes_requested ?? 0),
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
            'sws_open' => $this->swsOpenCount(),
            'deliveries_this_month' => $this->deliveriesThisMonth($monthStart, $monthEnd),
        ];
    }

    /**
     * @return array{
     *     metrics: array<string, int|float>,
     *     charts: array<string, mixed>,
     *     lists: array<string, Collection>
     * }
     */
    private function departmentBaseline(?int $departmentId, Carbon $monthStart, Carbon $monthEnd): array
    {
        $prs = $this->departmentPrsMetrics($departmentId, $monthStart, $monthEnd);
        $sws = $this->departmentSwsMetrics($departmentId, $monthStart, $monthEnd);

        return [
            'metrics' => [
                'dept_prs_this_month' => $prs['prs_this_month'],
                'dept_prs_open' => $prs['prs_open'],
                'dept_prs_on_hold' => $prs['prs_on_hold'],
                'dept_prs_completed' => $prs['prs_completed'],
                'dept_prs_total' => $prs['prs_total'],
                'dept_sws_this_month' => $sws['sws_this_month'],
                'dept_sws_open' => $sws['sws_open'],
            ],
            'charts' => [
                'dept_monthly_prs' => $this->monthlyPrsChart($departmentId),
                'dept_prs_status' => $this->prsStatusChart($departmentId),
            ],
            'lists' => [
                'dept_recent_prs' => $this->recentPrs($departmentId),
                'dept_recent_sws' => $this->recentStoreWithdrawals($departmentId),
            ],
        ];
    }

    /**
     * @param  array{
     *     metrics: array<string, int|float>,
     *     charts: array<string, mixed>,
     *     lists: array<string, Collection>
     * }  $payload
     * @return array{
     *     metrics: array<string, int|float>,
     *     charts: array<string, mixed>,
     *     lists: array<string, Collection>
     * }
     */
    private function mergeDepartmentBaseline(User $user, Carbon $monthStart, Carbon $monthEnd, array $payload): array
    {
        if ($user->department_id === null) {
            return $payload;
        }

        $baseline = $this->departmentBaseline($user->department_id, $monthStart, $monthEnd);

        return [
            'metrics' => array_merge($payload['metrics'], $baseline['metrics']),
            'charts' => array_merge($payload['charts'], $baseline['charts']),
            'lists' => array_merge($payload['lists'], $baseline['lists']),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function departmentPrsMetrics(?int $departmentId, Carbon $monthStart, Carbon $monthEnd): array
    {
        $monthStartDate = $monthStart->toDateString();
        $monthEndDate = $monthEnd->toDateString();

        $row = Prs::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->selectRaw('COUNT(*) as prs_total')
            ->selectRaw(
                'SUM(CASE WHEN prs_date >= ? AND prs_date <= ? THEN 1 ELSE 0 END) as prs_this_month',
                [$monthStartDate, $monthEndDate],
            )
            ->selectRaw("SUM(CASE WHEN status IN ('REQUESTED', 'CANVASSING', 'CANVASSER_HOLD', 'ON_HOLD', 'REVISED') THEN 1 ELSE 0 END) as prs_open")
            ->selectRaw("SUM(CASE WHEN status IN ('ON_HOLD', 'CANVASSER_HOLD') THEN 1 ELSE 0 END) as prs_on_hold")
            ->selectRaw("SUM(CASE WHEN status = 'PO_CREATED' THEN 1 ELSE 0 END) as prs_completed")
            ->first();

        return [
            'prs_this_month' => (int) ($row->prs_this_month ?? 0),
            'prs_open' => (int) ($row->prs_open ?? 0),
            'prs_on_hold' => (int) ($row->prs_on_hold ?? 0),
            'prs_completed' => (int) ($row->prs_completed ?? 0),
            'prs_total' => (int) ($row->prs_total ?? 0),
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
    private function departmentSwsMetrics(?int $departmentId, Carbon $monthStart, Carbon $monthEnd): array
    {
        $monthStartDate = $monthStart->toDateString();
        $monthEndDate = $monthEnd->toDateString();

        $row = DB::table('store_withdrawals')
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->selectRaw(
                'SUM(CASE WHEN sws_date >= ? AND sws_date <= ? THEN 1 ELSE 0 END) as sws_this_month',
                [$monthStartDate, $monthEndDate],
            )
            ->selectRaw('SUM(CASE WHEN approved_at IS NULL THEN 1 ELSE 0 END) as sws_open')
            ->first();

        return [
            'sws_this_month' => (int) ($row->sws_this_month ?? 0),
            'sws_open' => (int) ($row->sws_open ?? 0),
        ];
    }

    private function swsOpenCount(?int $departmentId = null): int
    {
        return (int) DB::table('store_withdrawals')
            ->whereNull('deleted_at')
            ->whereNull('approved_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->count();
    }

    private function docEntryPendingCount(): int
    {
        return (int) Cache::remember('dashboard.doc_entry_pending', self::CACHE_TTL_SECONDS, function (): int {
            $rrPending = (int) DB::table('receiving_reports as rr')
                ->leftJoin('accounting_doc_transactions as adt', function ($join): void {
                    $join->on('adt.doc_number', '=', 'rr.rr_number')
                        ->where('adt.doc_type', '=', 'RR');
                })
                ->whereNull('rr.deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('adt.status')
                        ->orWhere('adt.status', '!=', 'encoded');
                })
                ->count();

            $drPending = (int) DB::table('deliveries as dr')
                ->leftJoin('accounting_doc_transactions as adt', function ($join): void {
                    $join->on('adt.doc_number', '=', 'dr.dr_number')
                        ->where('adt.doc_type', '=', 'DR');
                })
                ->whereNull('dr.deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('adt.status')
                        ->orWhere('adt.status', '!=', 'encoded');
                })
                ->count();

            return $rrPending + $drPending;
        });
    }

    /**
     * ApexCharts heatmap: categories = department aliases, series = open statuses.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, int>}>}
     */
    private function openPrsHeatmapChart(int $topDepartments = 12): array
    {
        $openStatuses = ['REQUESTED', 'CANVASSING', 'CANVASSER_HOLD', 'ON_HOLD', 'REVISED'];

        $totals = Prs::query()
            ->whereIn('status', $openStatuses)
            ->whereNotNull('department_id')
            ->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')
            ->orderByDesc('total')
            ->limit($topDepartments)
            ->pluck('total', 'department_id');

        if ($totals->isEmpty()) {
            return [
                'categories' => [],
                'series' => collect($openStatuses)->map(fn (string $status) => [
                    'name' => str_replace('_', ' ', $status),
                    'data' => [],
                ])->values()->all(),
            ];
        }

        $orderedIds = $totals->keys()->all();

        $rows = Prs::query()
            ->whereIn('status', $openStatuses)
            ->whereIn('department_id', $orderedIds)
            ->selectRaw('department_id, status, COUNT(*) as total')
            ->groupBy('department_id', 'status')
            ->get();

        $aliases = Department::query()
            ->whereIn('id', $orderedIds)
            ->pluck('alias', 'id');

        $categories = array_map(
            fn ($id) => (string) ($aliases[$id] ?? '#'.$id),
            $orderedIds,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[strtoupper((string) $row->status)][(int) $row->department_id] = (int) $row->total;
        }

        $series = [];
        foreach ($openStatuses as $status) {
            $data = [];
            foreach ($orderedIds as $departmentId) {
                $data[] = (int) ($map[$status][$departmentId] ?? 0);
            }

            $series[] = [
                'name' => str_replace('_', ' ', $status),
                'data' => $data,
            ];
        }

        return compact('categories', 'series');
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
        $statuses = ['PENDING_APPROVAL', 'APPROVED'];

        $rows = PurchaseOrder::query()
            ->whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [strtoupper((string) $row->status) => (int) $row->total]);

        $labels = [];
        $series = [];
        foreach ($statuses as $status) {
            $labels[] = str_replace('_', ' ', $status);
            $series[] = (int) ($rows[$status] ?? 0);
        }

        return compact('labels', 'series');
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

    private function formatDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return Carbon::parse($date)->format('d M Y');
    }

    private function statusLabel(string $status): string
    {
        return str_replace('_', ' ', strtoupper($status));
    }

    private function statusTone(string $status): string
    {
        return match (strtoupper($status)) {
            'APPROVED', 'PO_CREATED', 'RECEIVED' => 'success',
            'PENDING_APPROVAL', 'REQUESTED', 'PENDING', 'OPEN' => 'warning',
            'CANVASSING' => 'info',
            'ON_HOLD', 'CANVASSER_HOLD', 'REVISED', 'CANCELLED', 'REJECTED' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * @return array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}
     */
    private function recentItem(
        string $title,
        string $subtitle,
        string $meta,
        string $status,
        ?string $tone = null,
    ): array {
        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'meta' => $meta,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'tone' => $tone ?? $this->statusTone($status),
        ];
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentPrs(?int $departmentId = null): Collection
    {
        return Prs::query()
            ->with('department:id,name,alias')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->latest('id')
            ->limit(5)
            ->get(['id', 'prs_number', 'status', 'prs_date', 'department_id'])
            ->map(fn (Prs $item) => $this->recentItem(
                title: (string) ($item->prs_number ?: '#'.$item->id),
                subtitle: $item->department?->name ?? 'No department',
                meta: $this->formatDate($item->prs_date),
                status: (string) $item->status,
            ));
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentPurchaseOrders(): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'po_number', 'status', 'total', 'supplier_id', 'created_at'])
            ->map(fn (PurchaseOrder $item) => $this->recentItem(
                title: (string) ($item->po_number ?: '#'.$item->id),
                subtitle: $item->supplier?->name ?? 'Unknown supplier',
                meta: 'Rp '.number_format((float) $item->total, 0, ',', '.'),
                status: (string) $item->status,
            ));
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentReceivingReports(): Collection
    {
        return ReceivingReport::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'rr_number', 'received_date', 'purchase_order_id'])
            ->map(fn (ReceivingReport $item) => $this->recentItem(
                title: (string) ($item->rr_number ?: '#'.$item->id),
                subtitle: $item->purchase_order_id ? 'PO #'.$item->purchase_order_id : 'No linked PO',
                meta: $this->formatDate($item->received_date),
                status: 'RECEIVED',
                tone: 'success',
            ));
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentTransferSlips(): Collection
    {
        return TransferSlip::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'ts_number', 'ts_date', 'approved_at'])
            ->map(function (TransferSlip $item) {
                $status = $item->approved_at ? 'APPROVED' : 'PENDING';

                return $this->recentItem(
                    title: (string) ($item->ts_number ?: '#'.$item->id),
                    subtitle: 'Transfer slip',
                    meta: $this->formatDate($item->ts_date),
                    status: $status,
                );
            });
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentDeliveries(): Collection
    {
        return Delivery::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'dr_number', 'dr_date', 'to_location'])
            ->map(fn (Delivery $item) => $this->recentItem(
                title: (string) ($item->dr_number ?: '#'.$item->id),
                subtitle: $item->to_location ?: 'No destination',
                meta: $this->formatDate($item->dr_date),
                status: 'DELIVERED',
                tone: 'info',
            ));
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentUsers(): Collection
    {
        return User::query()
            ->with('department:id,name,alias')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'username', 'email', 'department_id', 'created_at'])
            ->map(fn (User $item) => $this->recentItem(
                title: (string) $item->name,
                subtitle: '@'.$item->username.' · '.($item->department?->alias ?? 'No dept'),
                meta: $this->formatDate($item->created_at),
                status: 'ACTIVE',
                tone: 'secondary',
            ));
    }

    /**
     * @return array{
     *     online_count: int,
     *     session_count: int,
     *     items: Collection<int, array{name: string, username: string, department: string, last_seen: string, device: string, avatar: string}>
     * }
     */
    private function activeUsersSnapshot(): array
    {
        $threshold = now()->timestamp - Session::ONLINE_THRESHOLD_SECONDS;

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $threshold)
            ->orderByDesc('last_activity')
            ->get(['user_id', 'last_activity', 'ip_address', 'user_agent']);

        $sessionCount = $sessions->count();
        $latestByUser = $sessions->unique('user_id')->values();

        $users = User::query()
            ->with('department:id,name,alias')
            ->whereIn('id', $latestByUser->pluck('user_id'))
            ->get(['id', 'name', 'username', 'role', 'department_id'])
            ->keyBy('id');

        $items = $latestByUser
            ->take(8)
            ->map(function ($session) use ($users) {
                $user = $users->get($session->user_id);

                if ($user === null) {
                    return null;
                }

                $avatar = match ($user->role) {
                    'General Manager' => 'c2410c',
                    'Manager' => '4338ca',
                    'Supervisor' => '0e7490',
                    'Programmer' => '0284c7',
                    default => '475569',
                };

                return [
                    'name' => (string) $user->name,
                    'username' => (string) $user->username,
                    'department' => (string) ($user->department?->alias ?? $user->department?->name ?? '—'),
                    'last_seen' => Carbon::createFromTimestamp((int) $session->last_activity)->diffForHumans(),
                    'device' => User::deviceLabelFromUserAgent($session->user_agent),
                    'avatar' => $avatar,
                ];
            })
            ->filter()
            ->values();

        return [
            'online_count' => $latestByUser->count(),
            'session_count' => $sessionCount,
            'items' => $items,
        ];
    }

    /**
     * @return Collection<int, array{title: string, subtitle: string, meta: string, status: string, status_label: string, tone: string}>
     */
    private function recentStoreWithdrawals(?int $departmentId = null): Collection
    {
        return DB::table('store_withdrawals')
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'sws_number', 'sws_date', 'type', 'approved_at'])
            ->map(function ($item) {
                $status = $item->approved_at ? 'APPROVED' : 'OPEN';

                return $this->recentItem(
                    title: (string) ($item->sws_number ?: '#'.$item->id),
                    subtitle: strtoupper((string) ($item->type ?: 'normal')).' withdrawal',
                    meta: $this->formatDate($item->sws_date),
                    status: $status,
                );
            });
    }
}
