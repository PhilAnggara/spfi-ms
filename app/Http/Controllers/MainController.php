<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Prs;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\User;
use Carbon\Carbon;

class MainController extends Controller
{
    public function dashboard()
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $monthlyWindowStart = $now->copy()->startOfMonth()->subMonths(11);
        $monthlyWindowEnd = $now->copy()->endOfMonth();

        $monthlyPrsRows = Prs::query()
            ->whereBetween('prs_date', [$monthlyWindowStart->toDateString(), $monthlyWindowEnd->toDateString()])
            ->selectRaw('YEAR(prs_date) as year_num, MONTH(prs_date) as month_num, COUNT(*) as total')
            ->groupByRaw('YEAR(prs_date), MONTH(prs_date)')
            ->orderByRaw('YEAR(prs_date), MONTH(prs_date)')
            ->get();

        $monthlyPrsMap = $monthlyPrsRows
            ->mapWithKeys(function ($row) {
                $key = sprintf('%04d-%02d', (int) $row->year_num, (int) $row->month_num);
                return [$key => (int) $row->total];
            });

        $monthlyPrsLabels = [];
        $monthlyPrsSeries = [];

        for ($cursor = $monthlyWindowStart->copy(); $cursor->lte($monthlyWindowEnd); $cursor->addMonth()) {
            $monthKey = $cursor->format('Y-m');
            $monthlyPrsLabels[] = $cursor->format('M Y');
            $monthlyPrsSeries[] = (int) ($monthlyPrsMap[$monthKey] ?? 0);
        }

        $prsStatusOrder = ['REQUESTED', 'CANVASSING', 'ON_HOLD', 'REVISED', 'PO_CREATED'];
        $prsStatusRows = Prs::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $prsStatusMap = $prsStatusRows
            ->mapWithKeys(fn($row) => [strtoupper((string) $row->status) => (int) $row->total]);

        $prsStatusLabels = [];
        $prsStatusSeries = [];
        foreach ($prsStatusOrder as $status) {
            $prsStatusLabels[] = str_replace('_', ' ', $status);
            $prsStatusSeries[] = (int) ($prsStatusMap[$status] ?? 0);
        }

        $unknownStatuses = $prsStatusMap->keys()->diff($prsStatusOrder)->values();
        foreach ($unknownStatuses as $status) {
            $prsStatusLabels[] = str_replace('_', ' ', $status);
            $prsStatusSeries[] = (int) $prsStatusMap[$status];
        }

        $poStatusRows = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $poStatusLabels = $poStatusRows
            ->map(fn($row) => str_replace('_', ' ', strtoupper((string) $row->status)))
            ->values()
            ->all();
        $poStatusSeries = $poStatusRows
            ->map(fn($row) => (int) $row->total)
            ->values()
            ->all();

        $topSuppliersRows = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->whereNotNull('supplier_id')
            ->selectRaw('supplier_id, SUM(total) as total_value')
            ->groupBy('supplier_id')
            ->orderByDesc('total_value')
            ->limit(7)
            ->get();

        $topSupplierLabels = $topSuppliersRows
            ->map(fn($row) => optional($row->supplier)->name ?: 'Unknown Supplier')
            ->values()
            ->all();
        $topSupplierSeries = $topSuppliersRows
            ->map(fn($row) => (float) $row->total_value)
            ->values()
            ->all();

        $metrics = [
            'user_accounts' => User::query()->count(),
            'prs_this_month' => Prs::query()
                ->whereBetween('prs_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
            'po_approved_value_this_month' => (float) PurchaseOrder::query()
                ->where('status', 'APPROVED')
                ->whereBetween('approved_at', [$monthStart, $monthEnd])
                ->sum('total'),
            'rr_this_month' => ReceivingReport::query()
                ->whereBetween('received_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count(),
        ];

        $dashboardData = [
            'monthly_prs' => [
                'labels' => $monthlyPrsLabels,
                'series' => $monthlyPrsSeries,
            ],
            'prs_status' => [
                'labels' => $prsStatusLabels,
                'series' => $prsStatusSeries,
            ],
            'po_status' => [
                'labels' => $poStatusLabels,
                'series' => $poStatusSeries,
            ],
            'top_suppliers' => [
                'labels' => $topSupplierLabels,
                'series' => $topSupplierSeries,
            ],
        ];

        return view('pages.dashboard', [
            'metrics' => $metrics,
            'dashboardData' => $dashboardData,
            'dashboardMonthLabel' => Carbon::parse($monthStart)->translatedFormat('F Y'),
        ]);
    }

    public function cekCsv()
    {
        $data = [];
        return response()->json($data);
    }
}
