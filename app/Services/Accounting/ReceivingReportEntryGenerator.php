<?php

namespace App\Services\Accounting;

use App\Models\AccountingCode;
use App\Models\ReceivingReport;
use App\Services\CurrencyExchangeRateService;

class ReceivingReportEntryGenerator
{
    /**
     * @var array<string, string>
     */
    private const CATEGORY_ACCOUNT_MAP = [
        'OFFICE SUPPLIES' => '153',
        'PARTS' => '146',
        'SL/C' => '3047',
        'FACTORY SUPPLIES' => '145',
        'CHEM' => '4000',
        'FUEL' => '743',
        'LABEL' => '143',
        'CARTON' => '142',
        'INGREDIENTS' => '138',
        'CAN' => '140',
        'SPICES' => '138',
        'OTHERS' => '998',
        'RAW MATERIALS' => '137',
        'ZEBRA STOCK POT 80X80 CM' => '',
        'SPICES AND INGREDIENTS' => '138',
        'FISH' => '2003',
        'BC' => '',
        'FISHMEAL' => '257',
        'COAL' => '744',
        'SLUDGE OIL' => '745',
        'LABELING SUPPLIES' => '143',
        'CAPITAL GOODS' => '',
        'SCRAPS' => '',
        'FINISHED GOODS' => '129',
    ];

    public function __construct(
        private readonly CurrencyExchangeRateService $currencyExchangeRateService,
    ) {}

    /**
     * @return array{
     *     header: array<string, mixed>,
     *     lines: list<array{group_code: string, account_code: string, description: string|null, debit: float, credit: float}>,
     *     totals: array{total_debit: float, total_credit: float, variance: float, cost_code_total: float, acct_code_total: float},
     *     currency_conversion: array<string, mixed>,
     *     display: array<string, float>
     * }
     */
    public function generate(ReceivingReport $receivingReport, ?array $currencyConversion = null): array
    {
        $receivingReport->loadMissing([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
        ]);

        $po = $receivingReport->purchaseOrder;
        $currencyConversion ??= $this->currencyExchangeRateService->resolveConversionForPurchaseOrder(
            $po?->currency?->code,
            $receivingReport->received_date ?? $receivingReport->created_at,
        );

        $convertAmount = static function (float $amount) use ($currencyConversion): float {
            if (! ($currencyConversion['should_convert'] ?? false)) {
                return round($amount, 2);
            }

            return round($amount * (float) ($currencyConversion['multiplier'] ?? 1), 2);
        };

        $debitRows = [];
        $totalAmountAllItems = 0.0;
        $totalPpnAmountAllItems = 0.0;
        $totalPphAmountAllItems = 0.0;
        $creditAmountAllItems = 0.0;
        $isCapex = (bool) ($po?->items?->first()?->prsItem?->prs?->is_capex ?? false);

        foreach ($receivingReport->items as $rrItem) {
            $poItem = $rrItem->purchaseOrderItem;
            $item = $poItem?->item;
            $qtyTotal = (float) $rrItem->qty_good + (float) $rrItem->qty_bad;
            $lineAmounts = $this->resolveReceivedLineAmounts($poItem, $qtyTotal);
            $amount = $convertAmount($lineAmounts['base_amount']);
            $ppnAmount = $convertAmount($lineAmounts['ppn_amount']);
            $pphAmount = $convertAmount($lineAmounts['pph_amount']);
            $lineTotal = $convertAmount($lineAmounts['line_total']);

            $totalAmountAllItems += $amount;
            $totalPpnAmountAllItems += $ppnAmount;
            $totalPphAmountAllItems += $pphAmount;
            $creditAmountAllItems += $lineTotal;

            $categoryName = strtoupper(trim((string) ($item?->category?->name ?? 'OTHERS')));
            $groupCodeCategories = ['CHEM', 'SL/C'];
            $groupCode = in_array($categoryName, $groupCodeCategories, true)
                ? trim((string) ($poItem?->prsItem?->prs?->department?->code ?? ''))
                : '';
            $debitAccount = $isCapex
                ? '169'
                : (self::CATEGORY_ACCOUNT_MAP[$categoryName] ?? self::CATEGORY_ACCOUNT_MAP['OTHERS']);

            $groupKey = $groupCode.'|'.$debitAccount;
            if (! isset($debitRows[$groupKey])) {
                $debitRows[$groupKey] = [
                    'group_code' => $groupCode,
                    'account_code' => $debitAccount,
                    'debit' => 0.0,
                    'credit' => 0.0,
                ];
            }

            $debitRows[$groupKey]['debit'] += $amount;
        }

        $termOfPaymentType = $receivingReport->items
            ->map(fn ($rrItem) => strtolower(trim((string) data_get($rrItem, 'purchaseOrderItem.meta.term_of_payment_type', ''))))
            ->first(fn ($value) => $value !== '');

        $creditAccount = match ($termOfPaymentType) {
            'credit' => '201',
            'cash' => '148',
            default => '',
        };

        $lines = collect($debitRows)
            ->values()
            ->sortBy(fn (array $row) => sprintf('%s|%s', $row['group_code'], $row['account_code']))
            ->map(fn (array $row): array => [
                'group_code' => $row['group_code'],
                'account_code' => $row['account_code'],
                'description' => $this->resolveAccountDescription($row['account_code']),
                'debit' => round((float) $row['debit'], 4),
                'credit' => 0.0,
            ])
            ->values()
            ->all();

        if ($totalPpnAmountAllItems > 0) {
            $lines[] = [
                'group_code' => '',
                'account_code' => '551',
                'description' => $this->resolveAccountDescription('551'),
                'debit' => round($totalPpnAmountAllItems, 4),
                'credit' => 0.0,
            ];
        }

        if ($totalPphAmountAllItems > 0) {
            $lines[] = [
                'group_code' => '',
                'account_code' => '206',
                'description' => $this->resolveAccountDescription('206'),
                'debit' => 0.0,
                'credit' => round($totalPphAmountAllItems, 4),
            ];
        }

        $lines[] = [
            'group_code' => '',
            'account_code' => $creditAccount,
            'description' => $this->resolveAccountDescription($creditAccount),
            'debit' => 0.0,
            'credit' => round($creditAmountAllItems, 4),
        ];

        $totals = $this->calculateTotals($lines);

        return [
            'header' => [
                'doc_type' => 'RR',
                'doc_number' => (string) ($receivingReport->rr_number ?? ''),
                'doc_date' => $receivingReport->received_date?->toDateString()
                    ?? $receivingReport->created_at?->toDateString(),
                'po_number' => (string) ($po?->po_number ?? ''),
                'supplier_code' => (string) ($po?->supplier?->code ?? ''),
                'supplier_name' => (string) ($po?->supplier?->name ?? ''),
            ],
            'lines' => $lines,
            'totals' => $totals,
            'currency_conversion' => $currencyConversion,
            'display' => [
                'sub_total' => round($totalAmountAllItems, 2),
                'ppn_total' => round($totalPpnAmountAllItems, 2),
                'pph_total' => round($totalPphAmountAllItems, 2),
                'grand_total' => round($totalAmountAllItems + $totalPpnAmountAllItems - $totalPphAmountAllItems, 2),
            ],
        ];
    }

    /**
     * @param  list<array{group_code?: string, cost_center?: string, account_code: string, description?: string|null, debit: float, credit: float}>  $lines
     * @return array{total_debit: float, total_credit: float, variance: float, cost_code_total: float, acct_code_total: float}
     */
    public function calculateTotals(array $lines): array
    {
        $totalDebit = round(collect($lines)->sum(fn (array $line) => (float) ($line['debit'] ?? 0)), 4);
        $totalCredit = round(collect($lines)->sum(fn (array $line) => (float) ($line['credit'] ?? 0)), 4);

        $costCodeTotal = (float) collect($lines)->sum(function (array $line) {
            $groupCode = trim((string) ($line['group_code'] ?? $line['cost_center'] ?? ''));

            return is_numeric($groupCode) ? (float) $groupCode : 0;
        });

        $acctCodeTotal = (float) collect($lines)->sum(function (array $line) {
            $accountCode = trim((string) ($line['account_code'] ?? ''));

            return is_numeric($accountCode) ? (float) $accountCode : 0;
        });

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'variance' => round($totalDebit - $totalCredit, 4),
            'cost_code_total' => $costCodeTotal,
            'acct_code_total' => $acctCodeTotal,
        ];
    }

    public function resolveAccountDescription(?string $code): ?string
    {
        $normalizedCode = trim((string) $code);
        if ($normalizedCode === '') {
            return null;
        }

        static $cache = [];

        if (array_key_exists($normalizedCode, $cache)) {
            return $cache[$normalizedCode];
        }

        $account = AccountingCode::query()
            ->whereRaw('TRIM(code) = ?', [$normalizedCode])
            ->first();

        $cache[$normalizedCode] = $account?->desc ? trim((string) $account->desc) : null;

        return $cache[$normalizedCode];
    }

    /**
     * @return array{
     *     ordered_qty: float,
     *     discounted_unit_cost: float,
     *     base_amount: float,
     *     ppn_amount: float,
     *     pph_amount: float,
     *     line_total: float
     * }
     */
    public function resolveReceivedLineAmounts(mixed $poItem, float $qtyTotal): array
    {
        $orderedQty = (float) ($poItem?->quantity ?? 0);
        $receivedRatio = $orderedQty > 0 ? min(1, max(0, $qtyTotal / $orderedQty)) : 0;
        $lineSubtotal = (float) ($poItem?->line_subtotal ?? ($orderedQty * (float) ($poItem?->unit_price ?? 0)));
        $discountAmount = (float) ($poItem?->discount_amount ?? 0);
        $netLineAmount = max(0, $lineSubtotal - $discountAmount);
        $baseAmount = $netLineAmount * $receivedRatio;
        $discountedUnitCost = $orderedQty > 0
            ? $netLineAmount / $orderedQty
            : (float) ($poItem?->unit_price ?? 0);
        $ppnAmount = (float) ($poItem?->ppn_amount ?? 0) * $receivedRatio;
        $pphAmount = (float) ($poItem?->pph_amount ?? 0) * $receivedRatio;

        return [
            'ordered_qty' => $orderedQty,
            'discounted_unit_cost' => $discountedUnitCost,
            'base_amount' => $baseAmount,
            'ppn_amount' => $ppnAmount,
            'pph_amount' => $pphAmount,
            'line_total' => $baseAmount + $ppnAmount - $pphAmount,
        ];
    }

    /**
     * @param  list<array{group_code?: string, cost_center?: string, account?: string, account_code?: string, debit?: float|null, credit?: float|null}>  $entries
     * @return list<array{cost_center: string, account: string, debit: float|null, credit: float|null}>
     */
    public function formatEntriesForPdf(array $entries): array
    {
        return collect($entries)
            ->map(function (array $entry): array {
                $account = (string) ($entry['account_code'] ?? $entry['account'] ?? '');
                $groupCode = (string) ($entry['group_code'] ?? $entry['cost_center'] ?? '');
                $costCenter = preg_match('/^\d{4}$/', trim($account)) ? $groupCode : '';

                return [
                    'cost_center' => $costCenter,
                    'account' => $account,
                    'debit' => ($entry['debit'] ?? 0) > 0 ? (float) $entry['debit'] : null,
                    'credit' => ($entry['credit'] ?? 0) > 0 ? (float) $entry['credit'] : null,
                ];
            })
            ->values()
            ->all();
    }
}
