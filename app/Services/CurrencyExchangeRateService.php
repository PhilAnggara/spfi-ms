<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CurrencyExchangeRateService
{
    /**
     * @return list<string>
     */
    public function supportedCurrencyCodes(): array
    {
        return ['USD'];
    }

    public function supportedCurrencies(): Collection
    {
        return Currency::query()
            ->whereIn('code', $this->supportedCurrencyCodes())
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol']);
    }

    public function resolveSelectedCurrencyCode(?string $code): string
    {
        $normalizedCode = strtoupper(trim((string) $code));

        if (in_array($normalizedCode, $this->supportedCurrencyCodes(), true)) {
            return $normalizedCode;
        }

        return $this->supportedCurrencyCodes()[0];
    }

    public function resolveCurrencyId(string $code): int
    {
        $normalizedCode = strtoupper(trim($code));
        $currencyId = Currency::query()
            ->where('code', $normalizedCode)
            ->value('id');

        if (! $currencyId) {
            throw new \InvalidArgumentException("Currency code [{$normalizedCode}] was not found.");
        }

        return (int) $currencyId;
    }

    public function currentRate(string $code = 'USD'): ?CurrencyExchangeRate
    {
        $currencyId = $this->resolveCurrencyId($code);

        return CurrencyExchangeRate::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('currency_id', $currencyId)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    public function rateForDate(CarbonInterface|string $asOfDate, string $code = 'USD'): ?CurrencyExchangeRate
    {
        $normalizedCode = strtoupper(trim($code));

        if ($normalizedCode === 'IDR') {
            return null;
        }

        $currencyId = $this->resolveCurrencyId($normalizedCode);
        $date = Carbon::parse($asOfDate)->startOfDay();

        $exactRate = CurrencyExchangeRate::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('currency_id', $currencyId)
            ->whereDate('effective_date', $date->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($exactRate) {
            return $exactRate;
        }

        $pastRate = CurrencyExchangeRate::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('currency_id', $currencyId)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        if ($pastRate) {
            return $pastRate;
        }

        return CurrencyExchangeRate::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('currency_id', $currencyId)
            ->whereDate('effective_date', '>', $date->toDateString())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->first();
    }

    public function convertToIdr(float $amount, CarbonInterface|string $asOfDate, string $code = 'USD'): ?float
    {
        $normalizedCode = strtoupper(trim($code));

        if ($normalizedCode === 'IDR' || $normalizedCode === '') {
            return round($amount, 2);
        }

        $rate = $this->rateForDate($asOfDate, $normalizedCode);

        if (! $rate) {
            return null;
        }

        return round($amount * (float) $rate->rate_to_idr, 2);
    }

    /**
     * @return array{
     *     po_currency_code: string,
     *     should_convert: bool,
     *     rate_found: bool,
     *     rate_to_idr: float|null,
     *     effective_date: string|null,
     *     multiplier: float,
     *     rate_note: string|null
     * }
     */
    public function resolveConversionForPurchaseOrder(?string $currencyCode, CarbonInterface|string $asOfDate): array
    {
        $normalizedCode = strtoupper(trim((string) ($currencyCode ?: 'IDR')));

        if ($normalizedCode !== 'USD') {
            return [
                'po_currency_code' => $normalizedCode,
                'should_convert' => false,
                'rate_found' => false,
                'rate_to_idr' => null,
                'effective_date' => null,
                'multiplier' => 1.0,
                'rate_note' => null,
            ];
        }

        $rate = $this->rateForDate($asOfDate, 'USD');

        if (! $rate) {
            return [
                'po_currency_code' => $normalizedCode,
                'should_convert' => false,
                'rate_found' => false,
                'rate_to_idr' => null,
                'effective_date' => null,
                'multiplier' => 1.0,
                'rate_note' => 'No USD exchange rate available.',
            ];
        }

        return [
            'po_currency_code' => $normalizedCode,
            'should_convert' => true,
            'rate_found' => true,
            'rate_to_idr' => (float) $rate->rate_to_idr,
            'effective_date' => $rate->effective_date?->toDateString(),
            'multiplier' => (float) $rate->rate_to_idr,
            'rate_note' => sprintf(
                'USD rate %s (effective %s)',
                number_format((float) $rate->rate_to_idr, 4, '.', ','),
                $rate->effective_date?->format('d M Y') ?? '-'
            ),
        ];
    }

    public function paginateHistory(
        string $code = 'USD',
        int $perPage = 20,
        string $sortBy = 'effective_date',
        string $sortDirection = 'desc',
    ): LengthAwarePaginator {
        $currencyId = $this->resolveCurrencyId($code);
        $resolvedSort = $this->resolveHistorySort($sortBy, $sortDirection);
        $sortColumn = $resolvedSort['sort_by'];
        $direction = $resolvedSort['sort_direction'];

        return CurrencyExchangeRate::query()
            ->with(['createdBy', 'updatedBy'])
            ->where('currency_id', $currencyId)
            ->orderBy($sortColumn, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{sort_by: string, sort_direction: string}
     */
    public function resolveHistorySort(?string $sortBy, ?string $sortDirection): array
    {
        $normalizedSortBy = mb_strtolower(trim((string) $sortBy));
        $normalizedDirection = mb_strtolower(trim((string) $sortDirection));

        $allowedSortBy = [
            'effective_date',
            'rate_to_idr',
            'created_at',
        ];

        return [
            'sort_by' => in_array($normalizedSortBy, $allowedSortBy, true) ? $normalizedSortBy : 'effective_date',
            'sort_direction' => in_array($normalizedDirection, ['asc', 'desc'], true) ? $normalizedDirection : 'desc',
        ];
    }

    public function storeRate(
        float $rateToIdr,
        CarbonInterface|string $effectiveDate,
        ?string $notes = null,
        string $code = 'USD',
        ?int $userId = null,
    ): CurrencyExchangeRate {
        $authUserId = $userId ?? Auth::id();

        return CurrencyExchangeRate::query()->create([
            'currency_id' => $this->resolveCurrencyId($code),
            'rate_to_idr' => round($rateToIdr, 4),
            'effective_date' => Carbon::parse($effectiveDate)->toDateString(),
            'notes' => $notes,
            'created_by' => $authUserId,
            'updated_by' => $authUserId,
        ]);
    }
}
