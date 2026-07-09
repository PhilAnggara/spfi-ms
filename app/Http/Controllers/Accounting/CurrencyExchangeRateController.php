<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\CurrencyExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyExchangeRateController extends Controller
{
    public function __construct(
        private readonly CurrencyExchangeRateService $exchangeRateService,
    ) {}

    public function index(Request $request)
    {
        $selectedCurrency = $this->exchangeRateService->resolveSelectedCurrencyCode(
            $request->string('currency')->toString(),
        );
        $historySort = $this->exchangeRateService->resolveHistorySort(
            $request->string('sort_by')->toString(),
            $request->string('sort_direction')->toString(),
        );

        $currentRate = $this->exchangeRateService->currentRate($selectedCurrency);
        $history = $this->exchangeRateService->paginateHistory(
            $selectedCurrency,
            20,
            $historySort['sort_by'],
            $historySort['sort_direction'],
        )->appends([
            'currency' => $selectedCurrency,
            'sort_by' => $historySort['sort_by'],
            'sort_direction' => $historySort['sort_direction'],
        ]);

        $viewData = [
            'currentRate' => $currentRate,
            'history' => $history,
            'canUpdateRate' => $this->canUpdateRate($request),
            'selectedCurrency' => $selectedCurrency,
            'supportedCurrencies' => $this->exchangeRateService->supportedCurrencies(),
            'historySort' => $historySort,
        ];

        if ($request->ajax()) {
            return view('pages.accounting.exchange-rates.partials.history-panel', $viewData);
        }

        return view('pages.accounting.exchange-rates.index', $viewData);
    }

    public function store(Request $request)
    {
        if (! $this->canUpdateRate($request)) {
            abort(403, 'You are not allowed to update exchange rates.');
        }

        $validated = $request->validate([
            'currency_code' => ['required', 'string', Rule::in($this->exchangeRateService->supportedCurrencyCodes())],
            'rate_to_idr' => ['required', 'numeric', 'min:0.0001'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currencyCode = $validated['currency_code'];

        $this->exchangeRateService->storeRate(
            rateToIdr: (float) $validated['rate_to_idr'],
            effectiveDate: $validated['effective_date'],
            notes: $validated['notes'] ?? null,
            code: $currencyCode,
            userId: $request->user()?->id,
        );

        return redirect()
            ->route('accounting.exchange-rates.index', ['currency' => $currencyCode])
            ->with('success', "{$currencyCode} exchange rate has been saved successfully.");
    }

    private function canUpdateRate(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'administrator',
            'accounting-manager',
            'accounting-supervisor',
        ]);
    }
}
