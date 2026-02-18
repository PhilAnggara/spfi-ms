<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasingReportController extends Controller
{
    public function index()
    {
        $canvasers = User::role('canvaser')
            ->whereHas('department', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['purchasing']);
            })
            ->orderBy('name')
            ->get();

        return view('pages.procurement-reports', [
            'canvasers' => $canvasers,
        ]);
    }

    public function prsNotYetPo(Request $request)
    {
        $validated = $request->validate([
            'date_to' => ['required', 'date'],
            'canvaser_id' => ['nullable', 'exists:users,id'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $dateFrom = Carbon::parse($validated['date_to'])->subMonths(3)->toDateString();

        $itemsQuery = PrsItem::with([
            'prs.department',
            'item.unit',
            'canvaser',
        ])
            ->whereNull('purchase_order_id')
            ->whereHas('prs', function ($query) use ($validated, $dateFrom) {
                $query->whereDate('prs_date', '>=', $dateFrom)
                    ->whereDate('prs_date', '<=', $validated['date_to']);
            });

        if (! empty($validated['canvaser_id'])) {
            $itemsQuery->where('canvaser_id', $validated['canvaser_id']);
        }

        $rows = $itemsQuery
            ->orderBy('prs_id')
            ->orderBy('id')
            ->get()
            ->map(function ($item) {
                $prs = $item->prs;
                $department = $prs?->department;
                $unitName = $item->item?->unit?->name ?? '';

                return [
                    'prs_id' => $prs?->id,
                    'prs_number' => $prs?->prs_number,
                    'prs_date' => $prs?->prs_date,
                    'item_code' => $item->item?->code,
                    'item_name' => $item->item?->name,
                    'stock_on_hand' => $item->item?->stock_on_hand ?? 0,
                    'quantity' => $item->quantity,
                    'unit' => $unitName,
                    'department_code' => $department?->code,
                    'department_name' => $department?->name,
                ];
            });

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Requisition Slip (PRS) Not Yet Purchase Order (PO)',
            'as_of' => $validated['date_to'],
            'canvaser' => $this->canvaserName($validated['canvaser_id'] ?? null),
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.prs-not-yet-po',
            $data,
            'prs-not-yet-po'
        );
    }

    public function poNotYetDelivered(Request $request)
    {
        $validated = $request->validate([
            'date_to' => ['required', 'date'],
            'canvaser_id' => ['nullable', 'exists:users,id'],
            'po_type' => ['required', 'in:cash,credit'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $dateFrom = Carbon::parse($validated['date_to'])->subYear()->toDateString();

        $itemsQuery = PurchaseOrderItem::with([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'purchaseOrder.createdBy',
            'item.unit',
        ])
            ->whereHas('purchaseOrder', function ($query) use ($validated, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom)
                    ->whereDate('created_at', '<=', $validated['date_to']);
            })
            ->where('meta->term_of_payment_type', $validated['po_type']);

        if (! empty($validated['canvaser_id'])) {
            $itemsQuery->whereHas('purchaseOrder', function ($query) use ($validated) {
                $query->where('created_by', $validated['canvaser_id']);
            });
        }

        $rows = $itemsQuery->orderByDesc('id')->get()->map(function ($item) {
            $po = $item->purchaseOrder;
            $currencyCode = $po?->currency?->code ?? 'IDR';
            $amount = $this->calculateLineAmount($item, $po);
            $currencyBuckets = $this->currencyBuckets($currencyCode, $amount);

            return [
                'po_number' => $po?->po_number ?? ('#' . $po?->id),
                'po_date' => $po?->created_at?->toDateString(),
                'po_type' => $item->meta['term_of_payment_type'] ?? null,
                'currency' => $currencyCode,
                'supplier_code' => $po?->supplier?->code,
                'supplier_name' => $po?->supplier?->name,
                'item_code' => $item->item?->code,
                'item_name' => $item->item?->name,
                'quantity' => $item->quantity,
                'unit' => $item->item?->unit?->name ?? '',
                'unit_price' => $item->unit_price,
                'discount' => $this->calculateLineDiscount($item, $po),
                'pph' => $this->calculateLinePph($item, $po),
                'ppn' => $this->calculateLinePpn($item, $po),
                'amount' => $amount,
                'currency_buckets' => $currencyBuckets,
                'canvaser' => $po?->createdBy?->name,
                'remarks' => $item->notes ?? $po?->remark_text,
            ];
        });

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Order (PO) Not Yet Delivered',
            'as_of' => $validated['date_to'],
            'canvaser' => $this->canvaserName($validated['canvaser_id'] ?? null),
            'po_type' => $validated['po_type'],
            'rows' => $rows,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.po-not-yet-delivered',
            $data,
            'po-not-yet-delivered'
        );
    }

    public function poRegisteredPerPeriod(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'po_type' => ['required', 'in:all,confirmatory'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $itemsQuery = PurchaseOrderItem::with([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'purchaseOrder.createdBy',
            'prsItem.prs.department',
            'item.unit',
        ])
            ->whereHas('purchaseOrder', function ($query) use ($validated) {
                $query->whereBetween('created_at', [$validated['date_from'], $validated['date_to']]);
            });

        if ($validated['po_type'] === 'confirmatory') {
            $itemsQuery->whereHas('purchaseOrder', function ($query) {
                $query->where('remark_type', 'Confirmatory');
            });
        }

        $rows = $itemsQuery->orderByDesc('id')->get()->map(function ($item) {
            $po = $item->purchaseOrder;
            $prs = $item->prsItem?->prs;
            $department = $prs?->department;
            $currencyCode = $po?->currency?->code ?? 'IDR';
            $amount = $this->calculateLineAmount($item, $po);
            $currencyBuckets = $this->currencyBuckets($currencyCode, $amount);

            return [
                'prs_id' => $prs?->id,
                'prs_number' => $prs?->prs_number,
                'prs_date' => $prs?->prs_date,
                'department_code' => $department?->code,
                'department_name' => $department?->name,
                'po_number' => $po?->po_number ?? ('#' . $po?->id),
                'po_date' => $po?->created_at?->toDateString(),
                'currency' => $currencyCode,
                'supplier' => $po?->supplier?->name,
                'item_code' => $item->item?->code,
                'item_name' => $item->item?->name,
                'quantity' => $item->quantity,
                'unit' => $item->item?->unit?->name ?? '',
                'unit_price' => $item->unit_price,
                'discount' => $this->calculateLineDiscount($item, $po),
                'pph' => $this->calculateLinePph($item, $po),
                'ppn' => $this->calculateLinePpn($item, $po),
                'amount' => $amount,
                'currency_buckets' => $currencyBuckets,
                'canvaser' => $po?->createdBy?->name,
                'remarks' => $item->notes ?? $po?->remark_text,
            ];
        });

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Order (PO) Register Per Period',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'rows' => $rows,
            'totals' => $this->sumCurrencyBuckets($rows),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.po-registered-period',
            $data,
            'po-registered-period'
        );
    }

    public function poRegisteredPerDepartment(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $items = PurchaseOrderItem::with([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'purchaseOrder.createdBy',
            'prsItem.prs.department',
            'item.unit',
        ])
            ->whereHas('purchaseOrder', function ($query) use ($validated) {
                $query->whereBetween('created_at', [$validated['date_from'], $validated['date_to']]);
            })
            ->orderByDesc('id')
            ->get();

        $grouped = $items->groupBy(function ($item) {
            return $item->prsItem?->prs?->department?->code ?? 'UNKNOWN';
        });

        $groups = $grouped->map(function ($groupItems) {
            $department = $groupItems->first()?->prsItem?->prs?->department;

            $rows = $groupItems->map(function ($item) {
                $po = $item->purchaseOrder;
                $prs = $item->prsItem?->prs;
                $currencyCode = $po?->currency?->code ?? 'IDR';
                $amount = $this->calculateLineAmount($item, $po);
                $currencyBuckets = $this->currencyBuckets($currencyCode, $amount);

                return [
                    'prs_id' => $prs?->id,
                    'prs_number' => $prs?->prs_number,
                    'prs_date' => $prs?->prs_date,
                    'po_number' => $po?->po_number ?? ('#' . $po?->id),
                    'po_date' => $po?->created_at?->toDateString(),
                    'currency' => $currencyCode,
                    'supplier' => $po?->supplier?->name,
                    'item_code' => $item->item?->code,
                    'item_name' => $item->item?->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->item?->unit?->name ?? '',
                    'unit_price' => $item->unit_price,
                    'discount' => $this->calculateLineDiscount($item, $po),
                    'pph' => $this->calculateLinePph($item, $po),
                    'ppn' => $this->calculateLinePpn($item, $po),
                    'amount' => $amount,
                    'currency_buckets' => $currencyBuckets,
                    'canvaser' => $po?->createdBy?->name,
                    'remarks' => $item->notes ?? $po?->remark_text,
                ];
            });

            return [
                'department_code' => $department?->code,
                'department_name' => $department?->name,
                'rows' => $rows,
                'totals' => $this->sumCurrencyBuckets($rows),
            ];
        })->values();

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Order (PO) Register Per Department',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'groups' => $groups,
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.po-registered-department',
            $data,
            'po-registered-department'
        );
    }

    public function poRegisteredPerItem(Request $request)
    {
        $validated = $request->validate([
            'as_of' => ['required', 'date'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $asOf = Carbon::parse($validated['as_of'])->endOfDay();
        $monthStart = $asOf->copy()->startOfMonth();
        $yearStart = $asOf->copy()->startOfYear();

        $items = PurchaseOrderItem::with([
            'item.unit',
            'purchaseOrder.currency',
        ])
            ->whereHas('purchaseOrder', function ($query) use ($yearStart, $asOf) {
                $query->whereBetween('created_at', [$yearStart, $asOf]);
            })
            ->get();

        $rows = [];

        foreach ($items as $item) {
            $poDate = $item->purchaseOrder?->created_at;
            if (! $poDate) {
                continue;
            }

            $itemId = $item->item_id;
            $currencyCode = $item->purchaseOrder?->currency?->code ?? 'IDR';

            if (! isset($rows[$itemId])) {
                $rows[$itemId] = [
                    'item_code' => $item->item?->code,
                    'item_name' => $item->item?->name,
                    'unit' => $item->item?->unit?->name ?? '',
                    'current_qty' => 0,
                    'current_currency' => $this->emptyCurrencyBuckets(),
                    'ytd_qty' => 0,
                    'ytd_currency' => $this->emptyCurrencyBuckets(),
                ];
            }

            $isCurrentMonth = $poDate->between($monthStart, $asOf);

            $rows[$itemId]['ytd_qty'] += $item->quantity;
            $rows[$itemId]['ytd_currency'] = $this->addToCurrencyBuckets(
                $rows[$itemId]['ytd_currency'],
                $currencyCode,
                $item->total
            );

            if ($isCurrentMonth) {
                $rows[$itemId]['current_qty'] += $item->quantity;
                $rows[$itemId]['current_currency'] = $this->addToCurrencyBuckets(
                    $rows[$itemId]['current_currency'],
                    $currencyCode,
                    $item->total
                );
            }
        }

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Order (PO) Register Per Item',
            'as_of' => $validated['as_of'],
            'rows' => collect($rows)->values(),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.po-registered-item',
            $data,
            'po-registered-item'
        );
    }

    public function poRegisteredPerSupplier(Request $request)
    {
        $validated = $request->validate([
            'as_of' => ['required', 'date'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $asOf = Carbon::parse($validated['as_of'])->endOfDay();
        $monthStart = $asOf->copy()->startOfMonth();
        $yearStart = $asOf->copy()->startOfYear();

        $items = PurchaseOrder::with(['supplier', 'currency'])
            ->whereBetween('created_at', [$yearStart, $asOf])
            ->get();

        $rows = [];

        foreach ($items as $po) {
            $supplierId = $po->supplier_id;
            $currencyCode = $po->currency?->code ?? 'IDR';
            $poDate = $po->created_at;

            if (! isset($rows[$supplierId])) {
                $rows[$supplierId] = [
                    'supplier_code' => $po->supplier?->code,
                    'supplier_name' => $po->supplier?->name,
                    'current_currency' => $this->emptyCurrencyBuckets(),
                    'ytd_currency' => $this->emptyCurrencyBuckets(),
                ];
            }

            $rows[$supplierId]['ytd_currency'] = $this->addToCurrencyBuckets(
                $rows[$supplierId]['ytd_currency'],
                $currencyCode,
                $po->total
            );

            if ($poDate && $poDate->between($monthStart, $asOf)) {
                $rows[$supplierId]['current_currency'] = $this->addToCurrencyBuckets(
                    $rows[$supplierId]['current_currency'],
                    $currencyCode,
                    $po->total
                );
            }
        }

        $data = [
            'company' => 'PT. SINAR PURE FOODS INTERNATIONAL',
            'title' => 'Purchase Order (PO) Register Per Supplier',
            'as_of' => $validated['as_of'],
            'rows' => collect($rows)->values(),
        ];

        return $this->exportReport(
            $validated['format'],
            'exports.po-registered-supplier',
            $data,
            'po-registered-supplier'
        );
    }

    private function exportReport(string $format, string $view, array $data, string $filePrefix)
    {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $view, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function streamExcel(string $filePrefix, string $view, array $data): StreamedResponse
    {
        $filename = sprintf('%s-%s.xls', $filePrefix, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($view, $data) {
            echo view($view, $data)->render();
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel',
        ]);
    }

    private function canvaserName(?int $canvaserId): string
    {
        if (! $canvaserId) {
            return 'All';
        }

        return User::query()->find($canvaserId)?->name ?? 'All';
    }

    private function calculateLineDiscount(PurchaseOrderItem $item, ?PurchaseOrder $po): float
    {
        $rate = (float) ($po?->discount_rate ?? 0);
        return $item->total * ($rate / 100);
    }

    private function calculateLinePpn(PurchaseOrderItem $item, ?PurchaseOrder $po): float
    {
        $rate = (float) ($po?->ppn_rate ?? 0);
        $discount = $this->calculateLineDiscount($item, $po);
        $base = $item->total - $discount;
        return $base * ($rate / 100);
    }

    private function calculateLinePph(PurchaseOrderItem $item, ?PurchaseOrder $po): float
    {
        $rate = (float) ($po?->pph_rate ?? 0);
        $discount = $this->calculateLineDiscount($item, $po);
        $base = $item->total - $discount;
        return $base * ($rate / 100);
    }

    private function calculateLineAmount(PurchaseOrderItem $item, ?PurchaseOrder $po): float
    {
        $discount = $this->calculateLineDiscount($item, $po);
        $ppn = $this->calculateLinePpn($item, $po);
        $pph = $this->calculateLinePph($item, $po);

        return ($item->total - $discount) + $ppn - $pph;
    }

    private function emptyCurrencyBuckets(): array
    {
        return [
            'IDR' => 0,
            'PHP' => 0,
            'EUR' => 0,
            'GBP' => 0,
            'USD' => 0,
            'YEN' => 0,
        ];
    }

    private function currencyBuckets(string $currencyCode, float $amount): array
    {
        $buckets = $this->emptyCurrencyBuckets();
        $code = strtoupper($currencyCode);

        if (isset($buckets[$code])) {
            $buckets[$code] = $amount;
        }

        return $buckets;
    }

    private function addToCurrencyBuckets(array $buckets, string $currencyCode, float $amount): array
    {
        $code = strtoupper($currencyCode);
        if (isset($buckets[$code])) {
            $buckets[$code] += $amount;
        }

        return $buckets;
    }

    private function sumCurrencyBuckets($rows): array
    {
        $totals = $this->emptyCurrencyBuckets();

        foreach ($rows as $row) {
            foreach ($totals as $code => $value) {
                $totals[$code] += $row['currency_buckets'][$code] ?? 0;
            }
        }

        return $totals;
    }
}
