<?php

namespace App\Http\Controllers;

use App\Models\CustomsDocumentType;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportItem;
use App\Models\User;
use App\Services\Accounting\ReceivingReportEntryGenerator;
use App\Services\CurrencyExchangeRateService;
use App\Services\DocumentNumberService;
use App\Services\NotificationRecipientService;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivingReportController extends Controller
{
    private const MM_TO_PT = 2.834645669;

    public function index(Request $request)
    {
        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $receivingReports = $this->paginateReceivingReports($filters, 10);

        $totals = DB::table('receiving_report_items')
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->selectRaw('SUM(qty_good) as total_good, SUM(qty_bad) as total_bad')
            ->first();

        return view('pages.receiving-reports.index', [
            'receivingReports' => $receivingReports,
            'totalRr' => ReceivingReport::count(),
            'todayRr' => ReceivingReport::whereDate('received_date', now()->toDateString())->count(),
            'totalGood' => (float) ($totals->total_good ?? 0),
            'totalBad' => (float) ($totals->total_bad ?? 0),
            'filters' => $filters,
            'nextRrNumber' => app(DocumentNumberService::class)->previewNext('RR'),
            'customsDocumentTypes' => CustomsDocumentType::query()
                ->orderBy('name')
                ->whereLike('name', '%Pemasukan%')
                ->orWhere('code', 'BC 2.7')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function poByNumber(Request $request)
    {
        $validated = $request->validate([
            'po_number' => ['required', 'string', 'max:50'],
        ]);

        $purchaseOrder = PurchaseOrder::with([
            'supplier',
            'items.item.unit',
            'items.prsItem.prs',
        ])
            ->where('po_number', $validated['po_number'])
            ->first();

        if (! $purchaseOrder) {
            return response()->json([
                'message' => 'PO number not found.',
            ], 404);
        }

        if ($purchaseOrder->status !== 'APPROVED') {
            return response()->json([
                // 'message' => 'Only approved purchase orders can be used for receiving reports.',
                'message' => 'PO ini belum approve bu jadi belum bisa beking dpe RR. Mintol Purchasing ato IT approve akang jo dulu supaya boleh beking.',
            ], 422);
        }

        $receivedMap = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->whereIn('receiving_report_items.purchase_order_item_id', $purchaseOrder->items->pluck('id'))
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        $items = $purchaseOrder->items->map(function ($item) use ($receivedMap) {
            $qtyOrdered = (float) $item->quantity;
            $qtyReceived = (float) ($receivedMap[$item->id] ?? 0);
            $qtyRemaining = max(0, $qtyOrdered - $qtyReceived);

            return [
                'purchase_order_item_id' => $item->id,
                'item_code' => $item->item?->code,
                'item_name' => $item->item?->name,
                'unit_name' => $item->item?->unit?->name ?? 'PCS',
                'is_capex' => $this->isCapexPurchaseOrderItem($item),
                'qty_ordered' => $qtyOrdered,
                'qty_received' => $qtyReceived,
                'qty_remaining' => $qtyRemaining,
            ];
        })->values();

        return response()->json([
            'purchase_order' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'po_date' => optional($purchaseOrder->created_at)->format('Y-m-d'),
                'status' => $purchaseOrder->status,
                'supplier_name' => $purchaseOrder->supplier?->name,
                'is_capex' => $this->isCapexPurchaseOrderItem($purchaseOrder->items->first()),
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rr_number' => ['nullable', 'string', 'max:50'],
            'rr_number_suggested' => ['nullable', 'string', 'max:50'],
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'received_date' => ['required', 'date'],
            'requires_customs_document' => ['required', 'in:0,1'],
            'customs_document_number' => ['nullable', 'required_if:requires_customs_document,1', 'string', 'max:100'],
            'customs_document_type_id' => ['nullable', 'required_if:requires_customs_document,1', 'integer', 'exists:customs_document_types,id'],
            'customs_document_date' => ['nullable', 'required_if:requires_customs_document,1', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.selected' => ['nullable', 'in:0,1'],
            'items.*.qty_good' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_bad' => ['nullable', 'numeric', 'min:0'],
        ]);

        $requiresCustomsDocument = ($validated['requires_customs_document'] ?? '0') === '1';

        $purchaseOrder = PurchaseOrder::with([
            'items.item',
            'items.prsItem.prs',
        ])
            ->findOrFail($validated['purchase_order_id']);

        if ($purchaseOrder->status !== 'APPROVED') {
            return redirect()->back()->withErrors([
                'purchase_order_id' => 'Only approved purchase orders can be used for receiving reports.',
            ])->withInput();
        }

        $poItemIds = $purchaseOrder->items->pluck('id')->all();

        $selectedRows = collect($validated['items'])
            ->filter(function ($row) {
                return ($row['selected'] ?? '0') === '1';
            })
            ->sortBy(fn ($row) => (int) ($row['purchase_order_item_id'] ?? 0))
            ->values();

        if ($selectedRows->isEmpty()) {
            return redirect()->back()->withErrors([
                'items' => 'Please select at least one item to receive.',
            ])->withInput();
        }

        $receivedMap = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->whereIn('receiving_report_items.purchase_order_item_id', $poItemIds)
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        $poItemsById = $purchaseOrder->items->keyBy('id');
        $currentStockLines = $this->buildStockLinesFromSelectedRows($selectedRows, $poItemsById);

        foreach ($selectedRows as $row) {
            $poItemId = (int) $row['purchase_order_item_id'];

            if (! in_array($poItemId, $poItemIds, true)) {
                return redirect()->back()->withErrors([
                    'items' => 'Invalid PO item selected.',
                ])->withInput();
            }

            $qtyGood = (float) ($row['qty_good'] ?? 0);
            $qtyBad = (float) ($row['qty_bad'] ?? 0);
            $qtyInput = $qtyGood + $qtyBad;

            if ($qtyInput <= 0) {
                return redirect()->back()->withErrors([
                    'items' => 'Qty good/bad must be greater than 0 for selected items.',
                ])->withInput();
            }

            $ordered = (float) ($poItemsById[$poItemId]->quantity ?? 0);
            $received = (float) ($receivedMap[$poItemId] ?? 0);
            $remaining = max(0, $ordered - $received);

            if ($qtyInput > $remaining) {
                return redirect()->back()->withErrors([
                    'items' => 'Input quantity exceeds remaining quantity for one or more items.',
                ])->withInput();
            }
        }

        $numberService = app(DocumentNumberService::class);
        $receivingReport = null;
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolvedNumber = $numberService->resolve('RR', $validated['rr_number'] ?? null, $validated['rr_number_suggested'] ?? null);
            $numberService->assertUnique('RR', $resolvedNumber['number']);

            try {
                $receivingReport = DB::transaction(function () use ($validated, $selectedRows, $request, $currentStockLines, $requiresCustomsDocument, $resolvedNumber) {
                    $receivingReport = ReceivingReport::create([
                        'rr_number' => $resolvedNumber['number'],
                        'purchase_order_id' => $validated['purchase_order_id'],
                        'received_date' => $validated['received_date'],
                        'requires_customs_document' => $requiresCustomsDocument,
                        'customs_document_number' => $requiresCustomsDocument ? ($validated['customs_document_number'] ?? null) : null,
                        'customs_document_type_id' => $requiresCustomsDocument ? ($validated['customs_document_type_id'] ?? null) : null,
                        'customs_document_date' => $requiresCustomsDocument ? ($validated['customs_document_date'] ?? null) : null,
                        'notes' => $validated['notes'] ?? null,
                        'created_by' => $request->user()->id,
                    ]);

                    foreach ($selectedRows as $row) {
                        ReceivingReportItem::create([
                            'receiving_report_id' => $receivingReport->id,
                            'purchase_order_item_id' => $row['purchase_order_item_id'],
                            'qty_good' => (float) ($row['qty_good'] ?? 0),
                            'qty_bad' => (float) ($row['qty_bad'] ?? 0),
                        ]);
                    }

                    app(StockService::class)->applyReceivingReportAdjustment(
                        receivingReport: $receivingReport,
                        currentLines: $currentStockLines,
                        previousLines: [],
                        userId: $request->user()->id,
                    );

                    // Trigger PRS status check for all affected items
                    $this->checkPrsDeliveryStatus($receivingReport->purchase_order_id);

                    return $receivingReport;
                });
                break;
            } catch (QueryException $exception) {
                $canRetry = $resolvedNumber['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                throw $exception;
            }
        }

        $recipients = app(NotificationRecipientService::class)->uniqueUsers(
            app(NotificationRecipientService::class)->inventoryTeam(),
            app(NotificationRecipientService::class)->relatedPurchaseOrderUsers($purchaseOrder)
        );

        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'receiving_report_created',
            'title' => 'Receiving Report Created',
            'message' => 'RR #'.$receivingReport->rr_number.' has been created.',
            'action_url' => '/receiving-reports',
            'icon' => 'fa-light fa-truck-ramp-box',
            'icon_color' => 'bg-success',
            'meta' => [
                'receiving_report_id' => $receivingReport->id,
                'purchase_order_id' => $receivingReport->purchase_order_id,
            ],
        ]);

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been created.');
    }

    public function update(Request $request, ReceivingReport $receivingReport)
    {
        $validated = $request->validate([
            'received_date' => ['required', 'date'],
            'requires_customs_document' => ['required', 'in:0,1'],
            'customs_document_number' => ['nullable', 'required_if:requires_customs_document,1', 'string', 'max:100'],
            'customs_document_type_id' => ['nullable', 'required_if:requires_customs_document,1', 'integer', 'exists:customs_document_types,id'],
            'customs_document_date' => ['nullable', 'required_if:requires_customs_document,1', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.selected' => ['nullable', 'in:0,1'],
            'items.*.qty_good' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty_bad' => ['nullable', 'numeric', 'min:0'],
        ]);

        $requiresCustomsDocument = ($validated['requires_customs_document'] ?? '0') === '1';

        $receivingReport->load([
            'purchaseOrder.items.item',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item',
            'items.purchaseOrderItem.prsItem.prs',
        ]);

        $poItems = $receivingReport->purchaseOrder->items;
        $poItemIds = $poItems->pluck('id')->all();
        $poItemsById = $poItems->keyBy('id');
        $previousStockLines = $this->buildStockLinesFromReceivingReportItems($receivingReport->items);

        $selectedRows = collect($validated['items'])
            ->filter(function ($row) {
                return ($row['selected'] ?? '0') === '1';
            })
            ->sortBy(fn ($row) => (int) ($row['purchase_order_item_id'] ?? 0))
            ->values();

        if ($selectedRows->isEmpty()) {
            return redirect()->back()->withErrors([
                'items' => 'Please select at least one item to receive.',
            ])->withInput();
        }

        $currentStockLines = $this->buildStockLinesFromSelectedRows($selectedRows, $poItemsById);

        $receivedMapExcludingCurrent = ReceivingReportItem::query()
            ->join('receiving_reports', 'receiving_reports.id', '=', 'receiving_report_items.receiving_report_id')
            ->whereNull('receiving_reports.deleted_at')
            ->where('receiving_reports.id', '!=', $receivingReport->id)
            ->whereIn('receiving_report_items.purchase_order_item_id', $poItemIds)
            ->selectRaw('receiving_report_items.purchase_order_item_id, SUM(receiving_report_items.qty_good + receiving_report_items.qty_bad) as qty_received')
            ->groupBy('receiving_report_items.purchase_order_item_id')
            ->pluck('qty_received', 'receiving_report_items.purchase_order_item_id');

        foreach ($selectedRows as $row) {
            $poItemId = (int) $row['purchase_order_item_id'];

            if (! in_array($poItemId, $poItemIds, true)) {
                return redirect()->back()->withErrors([
                    'items' => 'Invalid PO item selected.',
                ])->withInput();
            }

            $qtyGood = (float) ($row['qty_good'] ?? 0);
            $qtyBad = (float) ($row['qty_bad'] ?? 0);
            $qtyInput = $qtyGood + $qtyBad;

            if ($qtyInput <= 0) {
                return redirect()->back()->withErrors([
                    'items' => 'Qty good/bad must be greater than 0 for selected items.',
                ])->withInput();
            }

            $ordered = (float) ($poItemsById[$poItemId]->quantity ?? 0);
            $receivedExcludingCurrent = (float) ($receivedMapExcludingCurrent[$poItemId] ?? 0);
            $remaining = max(0, $ordered - $receivedExcludingCurrent);

            if ($qtyInput > $remaining) {
                return redirect()->back()->withErrors([
                    'items' => 'Input quantity exceeds remaining quantity for one or more items.',
                ])->withInput();
            }
        }

        try {
            DB::transaction(function () use ($receivingReport, $validated, $selectedRows, $request, $currentStockLines, $previousStockLines, $requiresCustomsDocument) {
                $receivingReport->update([
                    'received_date' => $validated['received_date'],
                    'requires_customs_document' => $requiresCustomsDocument,
                    'customs_document_number' => $requiresCustomsDocument ? ($validated['customs_document_number'] ?? null) : null,
                    'customs_document_type_id' => $requiresCustomsDocument ? ($validated['customs_document_type_id'] ?? null) : null,
                    'customs_document_date' => $requiresCustomsDocument ? ($validated['customs_document_date'] ?? null) : null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Soft-delete would collide with unique(rr_id, po_item_id) when recreating lines.
                $receivingReport->items()->forceDelete();

                foreach ($selectedRows as $row) {
                    ReceivingReportItem::create([
                        'receiving_report_id' => $receivingReport->id,
                        'purchase_order_item_id' => $row['purchase_order_item_id'],
                        'qty_good' => (float) ($row['qty_good'] ?? 0),
                        'qty_bad' => (float) ($row['qty_bad'] ?? 0),
                    ]);
                }

                app(StockService::class)->applyReceivingReportAdjustment(
                    receivingReport: $receivingReport,
                    currentLines: $currentStockLines,
                    previousLines: $previousStockLines,
                    userId: $request->user()->id,
                );

                // Trigger PRS status check for all affected items
                $this->checkPrsDeliveryStatus($receivingReport->purchase_order_id);
            });
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        $receivingReport->loadMissing('purchaseOrder.items.prsItem.prs.user');
        $recipients = app(NotificationRecipientService::class)->uniqueUsers(
            app(NotificationRecipientService::class)->inventoryTeam(),
            app(NotificationRecipientService::class)->relatedPurchaseOrderUsers($receivingReport->purchaseOrder)
        );

        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'receiving_report_updated',
            'title' => 'Receiving Report Updated',
            'message' => 'RR #'.$receivingReport->rr_number.' has been updated.',
            'action_url' => '/receiving-reports',
            'icon' => 'fa-light fa-pen-to-square',
            'icon_color' => 'bg-info',
            'meta' => [
                'receiving_report_id' => $receivingReport->id,
                'purchase_order_id' => $receivingReport->purchase_order_id,
            ],
        ]);

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been updated successfully.');
    }

    public function destroy(ReceivingReport $receivingReport)
    {
        $receivingReport->load([
            'items.purchaseOrderItem.item',
            'items.purchaseOrderItem.prsItem.prs',
        ]);

        $releasedNumber = $receivingReport->rr_number;

        DB::transaction(function () use ($receivingReport) {
            app(StockService::class)->purgeDocumentMovementsAndRechain(
                StockService::REF_RECEIVING_REPORT,
                (int) $receivingReport->id,
            );

            $receivingReport->items()->delete();

            $receivingReport->update([
                'rr_number' => 'DELETED-'.$receivingReport->id,
            ]);

            $receivingReport->delete();

            $this->checkPrsDeliveryStatus($receivingReport->purchase_order_id);
        });

        $receivingReport->loadMissing('purchaseOrder.items.prsItem.prs.user');
        $recipients = app(NotificationRecipientService::class)->uniqueUsers(
            app(NotificationRecipientService::class)->inventoryTeam(),
            app(NotificationRecipientService::class)->relatedPurchaseOrderUsers($receivingReport->purchaseOrder)
        );

        app(NotificationRecipientService::class)->notify($recipients, [
            'type' => 'receiving_report_deleted',
            'title' => 'Receiving Report Deleted',
            'message' => 'RR #'.($releasedNumber ?: '-').' has been deleted.',
            'action_url' => '/receiving-reports',
            'icon' => 'fa-light fa-trash-can',
            'icon_color' => 'bg-danger',
            'meta' => [
                'receiving_report_id' => $receivingReport->id,
                'purchase_order_id' => $receivingReport->purchase_order_id,
            ],
        ]);

        return redirect()
            ->route('receiving-reports.index')
            ->with('success', 'Receiving report has been deleted. The RR number was released for reuse.');
    }

    public function print(Request $request, ReceivingReport $receivingReport)
    {
        $mode = $request->input('mode', $request->query('mode', 'print'));
        $isPreview = $mode !== 'print';

        if ($request->isMethod('post') || $request->filled('rr_number')) {
            $this->saveRrNumberFromRequest($request, $receivingReport);
            $receivingReport->refresh();
        }

        if (! $isPreview && trim((string) ($receivingReport->rr_number ?? '')) === '') {
            return redirect()->back()->withErrors(['message' => 'RR number is required before printing.']);
        }

        $receivingReport->load([
            'purchaseOrder.supplier',
            'purchaseOrder.currency',
            'purchaseOrder.items.prsItem.prs',
            'items.purchaseOrderItem.item.unit',
            'items.purchaseOrderItem.item.category',
            'items.purchaseOrderItem.prsItem.prs.department',
            'customsDocumentType',
            'createdBy',
        ]);

        $currencyConversion = app(CurrencyExchangeRateService::class)->resolveConversionForPurchaseOrder(
            $receivingReport->purchaseOrder?->currency?->code,
            $receivingReport->received_date ?? $receivingReport->created_at,
        );

        $rrAccountingPayload = app(ReceivingReportEntryGenerator::class)->generate(
            $receivingReport,
            $currencyConversion,
        );

        $imManager = User::query()
            ->whereHas('department', function ($query) {
                $query->where('name', 'Inventory Management');
            })
            ->where('role', 'Manager')
            ->orderBy('name')
            ->first();

        $backgroundImagePath = public_path('assets/images/Blank RR.jpg');
        $backgroundImageDataUri = null;
        if ($isPreview && is_readable($backgroundImagePath)) {
            $backgroundImageDataUri = 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($backgroundImagePath));
        }

        $pageWidthMm = (float) config('receiving-report.paper.width_mm', 215);
        $pageHeightMm = (float) config('receiving-report.paper.height_mm', 160);

        $filename = sprintf(
            'RR-%s-%s.pdf',
            $receivingReport->rr_number ?? $receivingReport->id,
            now()->format('YmdHis')
        );

        $pdf = Pdf::loadView('pdf.receiving-report', [
            'receivingReport' => $receivingReport,
            'isPreview' => $isPreview,
            'approvedByName' => $imManager?->name,
            'backgroundImageDataUri' => $backgroundImageDataUri,
            'pageWidthMm' => $pageWidthMm,
            'pageHeightMm' => $pageHeightMm,
            'currencyConversion' => $currencyConversion,
            'rrAccountingPayload' => $rrAccountingPayload,
        ])
            ->setPaper([
                0,
                0,
                $pageWidthMm * self::MM_TO_PT,
                $pageHeightMm * self::MM_TO_PT,
            ]);

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        if ($canvas instanceof \Dompdf\Adapter\CPDF) {
            $canvas->get_cpdf()->setPreferences('PrintScaling', 'None');
        }

        return $pdf->stream($filename);
    }

    private function saveRrNumberFromRequest(Request $request, ReceivingReport $receivingReport): void
    {
        $validated = $request->validate([
            'rr_number' => ['nullable', 'string', 'max:50'],
            'rr_number_suggested' => ['nullable', 'string', 'max:50'],
        ]);

        $numberService = app(DocumentNumberService::class);
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $resolvedNumber = $numberService->resolve('RR', $validated['rr_number'] ?? null, $validated['rr_number_suggested'] ?? null);
            $numberService->assertUnique('RR', $resolvedNumber['number'], $receivingReport->id);

            try {
                $receivingReport->update([
                    'rr_number' => $resolvedNumber['number'],
                ]);
                break;
            } catch (QueryException $exception) {
                $canRetry = $resolvedNumber['source'] === 'auto'
                    && $attempt < $maxAttempts
                    && $numberService->isDuplicateNumberException($exception);

                if ($canRetry) {
                    continue;
                }

                throw $exception;
            }
        }
    }

    /**
     * Check and update PRS delivery status for all items related to a PO
     */
    private function checkPrsDeliveryStatus($purchaseOrderId)
    {
        $purchaseOrder = PurchaseOrder::with(['items.prsItem.prs'])
            ->find($purchaseOrderId);

        if (! $purchaseOrder) {
            return;
        }

        // Collect all unique PRS records from the PO items
        $prsRecords = $purchaseOrder->items
            ->pluck('prsItem.prs')
            ->whereNotNull()
            ->unique('id');

        // Check and update status for each PRS
        foreach ($prsRecords as $prs) {
            $prs->checkAndUpdateDeliveryStatus();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $selectedRows
     * @param  \Illuminate\Support\Collection<int, \App\Models\PurchaseOrderItem>  $poItemsById
     * @return array<int, array<string, mixed>>
     */
    private function buildStockLinesFromSelectedRows($selectedRows, $poItemsById): array
    {
        $lines = [];

        foreach ($selectedRows as $row) {
            $purchaseOrderItemId = (int) ($row['purchase_order_item_id'] ?? 0);
            if ($purchaseOrderItemId <= 0 || ! isset($poItemsById[$purchaseOrderItemId])) {
                continue;
            }

            $purchaseOrderItem = $poItemsById[$purchaseOrderItemId];
            if ($this->isCapexPurchaseOrderItem($purchaseOrderItem)) {
                continue;
            }

            $item = $purchaseOrderItem->item;

            if (! $item) {
                continue;
            }

            $lines[$purchaseOrderItemId] = [
                'purchase_order_item_id' => $purchaseOrderItemId,
                'item_id' => (int) $item->id,
                'product_code' => (string) $item->code,
                'qty_good' => (float) ($row['qty_good'] ?? 0),
                'unit_price' => (float) ($purchaseOrderItem->unit_price ?? 0),
                'wh_code' => 'MAIN',
            ];
        }

        return $lines;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ReceivingReportItem>  $receivingReportItems
     * @return array<int, array<string, mixed>>
     */
    private function buildStockLinesFromReceivingReportItems($receivingReportItems): array
    {
        $lines = [];

        foreach ($receivingReportItems as $receivingReportItem) {
            $purchaseOrderItem = $receivingReportItem->purchaseOrderItem;
            $item = $purchaseOrderItem?->item;

            if (! $purchaseOrderItem || ! $item) {
                continue;
            }

            if ($this->isCapexPurchaseOrderItem($purchaseOrderItem)) {
                continue;
            }

            $purchaseOrderItemId = (int) $purchaseOrderItem->id;

            $lines[$purchaseOrderItemId] = [
                'purchase_order_item_id' => $purchaseOrderItemId,
                'item_id' => (int) $item->id,
                'product_code' => (string) $item->code,
                'qty_good' => (float) $receivingReportItem->qty_good,
                'unit_price' => (float) ($purchaseOrderItem->unit_price ?? 0),
                'wh_code' => 'MAIN',
            ];
        }

        return $lines;
    }

    /**
     * SQL Server-safe pagination for receiving reports.
     */
    private function paginateReceivingReports(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $currentPage = max(1, (int) LengthAwarePaginator::resolveCurrentPage());

        $keyword = mb_strtolower(trim((string) ($filters['keyword'] ?? '')));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $keywordLike = "%{$keyword}%";

        $query = DB::table('receiving_reports as rr')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'rr.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'rr.created_by')
            ->whereNull('rr.deleted_at')
            ->select('rr.id as id')
            ->when($keyword !== '', function ($subQuery) use ($keywordLike) {
                $subQuery->where(function ($keywordQuery) use ($keywordLike) {
                    $keywordQuery->whereRaw("LOWER(COALESCE(rr.rr_number, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(po.po_number, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(s.name, '')) LIKE ?", [$keywordLike])
                        ->orWhereRaw("LOWER(COALESCE(u.name, '')) LIKE ?", [$keywordLike]);
                });
            })
            ->when($dateFrom !== '', function ($subQuery) use ($dateFrom) {
                $subQuery->whereDate('rr.received_date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($subQuery) use ($dateTo) {
                $subQuery->whereDate('rr.received_date', '<=', $dateTo);
            })
            ->orderByDesc('rr.received_date')
            ->orderByDesc('rr.id');

        $total = (clone $query)->reorder()->count();
        $ids = [];

        if ($this->isSqlServer()) {
            $startRow = (($currentPage - 1) * $perPage) + 1;
            $endRow = $currentPage * $perPage;

            $rankedIdsQuery = (clone $query)
                ->reorder()
                ->select('rr.id as id')
                ->selectRaw('ROW_NUMBER() OVER (ORDER BY rr.received_date DESC, rr.id DESC) as row_num');

            $ids = DB::query()
                ->fromSub($rankedIdsQuery, 'ranked_rr')
                ->whereBetween('row_num', [$startRow, $endRow])
                ->orderBy('row_num')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $ids = (clone $query)
                ->forPage($currentPage, $perPage)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = ReceivingReport::with([
                'purchaseOrder.supplier',
                'purchaseOrder.items.item.unit',
                'items.purchaseOrderItem.item.unit',
                'items.purchaseOrderItem.prsItem.prs',
                'customsDocumentType',
                'createdBy',
            ])
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $collection = collect($ids)
                ->map(fn (int $id) => $itemsById->get($id))
                ->filter()
                ->values();
        }

        return new LengthAwarePaginator(
            items: $collection,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

    private function isCapexPurchaseOrderItem($purchaseOrderItem): bool
    {
        return (bool) ($purchaseOrderItem?->prsItem?->prs?->is_capex ?? false);
    }
}
