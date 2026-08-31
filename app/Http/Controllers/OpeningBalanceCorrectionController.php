<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceCorrectionRequest;
use App\Models\OpeningBalanceCorrection;
use App\Services\DocumentNumberService;
use App\Services\OpeningBalanceCorrectionService;
use App\Support\Concerns\PaginatesLegacySqlServer;
use App\Support\Concerns\SearchesStockCorrectionItems;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpeningBalanceCorrectionController extends Controller
{
    use PaginatesLegacySqlServer;
    use SearchesStockCorrectionItems;

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('keyword'));
        $status = trim((string) $request->query('status'));
        $period = trim((string) $request->query('period'));

        if ($period !== '' && ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = '';
        }

        $correctionsQuery = OpeningBalanceCorrection::query()
            ->with(['createdBy:id,name', 'items'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($inner) use ($keyword) {
                    $inner->where('obc_number', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%");
                });
            })
            ->when($status === 'posted', function ($query) {
                $query->whereNull('reversed_at');
            })
            ->when($status === 'reversed', function ($query) {
                $query->whereNotNull('reversed_at');
            })
            ->when($period !== '', function ($query) use ($period) {
                [$year, $month] = array_map('intval', explode('-', $period));
                $query->whereYear('period_month', $year)
                    ->whereMonth('period_month', $month);
            });

        $orderClause = 'opening_balance_corrections.period_month DESC, opening_balance_corrections.id DESC';
        $correctionsQuery->orderByDesc('period_month')->orderByDesc('id');

        $corrections = $this->paginateEloquentForCurrentConnection($correctionsQuery, $orderClause, 20);

        return view('pages.opening-balance-corrections.index', [
            'corrections' => $corrections,
            'filters' => [
                'keyword' => $keyword,
                'status' => $status,
                'period' => $period,
            ],
        ]);
    }

    public function create(DocumentNumberService $numberService): View
    {
        return view('pages.opening-balance-corrections.create', [
            'nextObcNumber' => $numberService->previewNext('OBC'),
            'itemSearchUrl' => route('opening-balance-corrections.items.search'),
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        return $this->searchStockCorrectionItems($request);
    }

    public function preview(
        Request $request,
        OpeningBalanceCorrectionService $correctionService
    ): JsonResponse {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.new_beginning' => ['required', 'numeric', 'min:0'],
            'items.*.wh_code' => ['nullable', 'string', 'max:20'],
        ]);

        $periodMonth = Carbon::createFromFormat('Y-m', $validated['period_month'])->startOfMonth()->toDateString();

        return response()->json([
            'previews' => $correctionService->preview($periodMonth, $validated['items']),
        ]);
    }

    public function store(
        StoreOpeningBalanceCorrectionRequest $request,
        DocumentNumberService $numberService,
        OpeningBalanceCorrectionService $correctionService
    ): RedirectResponse {
        $periodMonth = Carbon::createFromFormat('Y-m', $request->validated('period_month'))
            ->startOfMonth()
            ->toDateString();

        $correction = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolved = $numberService->resolve(
                'OBC',
                $request->input('obc_number'),
                $request->input('obc_number_suggested')
            );
            $obcNumber = $resolved['number'];
            $numberService->assertUnique('OBC', $obcNumber);

            try {
                $correction = DB::transaction(function () use ($request, $obcNumber, $periodMonth, $correctionService) {
                    $correction = OpeningBalanceCorrection::query()->create([
                        'obc_number' => $obcNumber,
                        'period_month' => $periodMonth,
                        'reason' => $request->validated('reason'),
                        'allow_negative_balance' => $request->boolean('allow_negative_balance'),
                        'created_by' => $request->user()->id,
                        'updated_by' => $request->user()->id,
                    ]);

                    $correctionService->apply(
                        correction: $correction,
                        lines: $request->validated('items'),
                        userId: $request->user()->id,
                    );

                    return $correction;
                });
                break;
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (QueryException $exception) {
                $canRetry = $resolved['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                if ($numberService->isDuplicateNumberException($exception)) {
                    $numberService->assertUnique('OBC', $obcNumber);

                    throw ValidationException::withMessages([
                        'obc_number' => "The OBC Number {$obcNumber} has already been used.",
                    ]);
                }

                throw $exception;
            }
        }

        return redirect()
            ->route('opening-balance-corrections.show', $correction)
            ->with('success', "Opening balance correction {$correction->obc_number} applied and movements rebuilt.");
    }

    public function show(OpeningBalanceCorrection $openingBalanceCorrection): View
    {
        $openingBalanceCorrection->load([
            'items.item:id,name,code',
            'createdBy:id,name',
            'reversedBy:id,name',
        ]);

        return view('pages.opening-balance-corrections.show', [
            'correction' => $openingBalanceCorrection,
        ]);
    }

    public function reverse(
        OpeningBalanceCorrection $openingBalanceCorrection,
        OpeningBalanceCorrectionService $correctionService
    ): RedirectResponse {
        $openingBalanceCorrection->load('items');

        $correctionService->reverse(
            correction: $openingBalanceCorrection,
            userId: auth()->id(),
        );

        return redirect()
            ->route('opening-balance-corrections.show', $openingBalanceCorrection)
            ->with('success', "Opening balance correction {$openingBalanceCorrection->obc_number} reversed. Stock rebuilt to previous beginning and ADJ ledger cleared.");
    }
}
