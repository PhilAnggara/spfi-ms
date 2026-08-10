<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardDataService;
use App\Services\Dashboard\DashboardResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MainController extends Controller
{
    public function dashboard(
        Request $request,
        DashboardResolver $resolver,
        DashboardDataService $dataService,
    ): View {
        $user = $request->user();
        $key = $resolver->resolve($user);
        $dashboard = $dataService->build($user, $key);

        return view("pages.dashboard.{$key}", [
            'dashboard' => $dashboard,
            'metrics' => $dashboard['metrics'],
            'dashboardData' => $dashboard['charts'],
            'dashboardMonthLabel' => $dashboard['month_label'],
        ]);
    }

    public function openPrsHeatmap(DashboardDataService $dataService): JsonResponse
    {
        return response()->json($dataService->openPrsHeatmapCached());
    }

    public function cekCsv()
    {
        $data = [];

        return response()->json($data);
    }
}
