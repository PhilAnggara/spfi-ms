<?php

namespace App\Http\Controllers;

use App\Exports\ImDeliveryRegisterSpreadsheet;
use App\Exports\ImReceivingRegisterSpreadsheet;
use App\Exports\ImStockInventorySpreadsheet;
use App\Exports\ImSwsRegisterSpreadsheet;
use App\Exports\ImTransactionSpreadsheet;
use App\Exports\ImTransferRegisterSpreadsheet;
use App\Models\Department;
use App\Models\Item;
use App\Support\PdfReport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImReportController extends Controller
{
    private const CATEGORIES = [
        'OFFICE SUPPLIES',
        'SPARE PARTS',
        'FACTORY SUPPLIES',
        'CHEMICAL',
        'FUEL',
        'LABEL',
        'CARTON',
        'CAN',
        'RAW MATERIALS',
        'SPICES AND INGREDIENTS',
        'COAL',
        'SLUDGE OIL',
        'LABELING SUPPLIES',
        'MATERIAL IN TRANSIT',
        'FINISHED GOODS',
        'FISH',
    ];

    private const TS_TYPES = [
        'all' => 'All type',
        'normal' => 'Normal',
        'others' => 'Others',
    ];

    public function index(): View
    {
        $departments = Department::query()
            ->orderBy('name')
            ->get();

        return view('pages.im-reports', [
            'departments' => $departments,
            'categories' => self::CATEGORIES,
            'tsTypes' => self::TS_TYPES,
        ]);
    }

    public function stockInventory(Request $request): Response
    {
        $validated = $request->validate([
            'as_of' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $rows = $this->stockInventoryRows($validated['as_of'], $validated['category']);

        $data = [
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Stock Inventory per Category',
            'as_of' => $validated['as_of'],
            'category' => $validated['category'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $rows,
            'prepared_by_name' => $request->user()?->name ?? '',
            'prepared_by_title' => '',
            'checked_by_name' => 'Daniel Watuna',
            'checked_by_title' => 'IM Supervisor',
            'approved_by_name' => 'Rommy Tendean',
            'approved_by_title' => 'IM Manager',
        ];

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-stock-inventory-%s.xlsx', now()->format('Ymd-His'));

            return (new ImStockInventorySpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-stock-inventory-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-stock-inventory', $data, $filename);
    }

    public function transaction(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:'.implode(',', self::CATEGORIES)],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $groups = $this->transactionGroups(
            $validated['date_from'],
            $validated['date_to'],
            $validated['category']
        );

        $data = [
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Transaction Report per Category',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'category' => $validated['category'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'groups' => $groups,
            'prepared_by_name' => $request->user()?->name ?? '',
            'prepared_by_title' => '',
            'checked_by_name' => 'Daniel Watuna',
            'checked_by_title' => 'IM Supervisor',
            'approved_by_name' => 'Rommy Tendean',
            'approved_by_title' => 'IM Manager',
        ];

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-transaction-%s.xlsx', now()->format('Ymd-His'));

            return (new ImTransactionSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-transaction-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-transaction', $data, $filename);
    }

    public function receivingRegister(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $data = array_merge([
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Receiving Report Register',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $this->receivingRegisterRows($validated['date_from'], $validated['date_to']),
        ], $this->reportSignatories($request));

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-receiving-register-%s.xlsx', now()->format('Ymd-His'));

            return (new ImReceivingRegisterSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-receiving-register-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-receiving-register', $data, $filename);
    }

    public function swsRegister(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $departmentLabel = 'All departments';
        if (! empty($validated['department_id'])) {
            $department = Department::query()->find($validated['department_id']);
            $departmentLabel = $department
                ? trim(($department->code ? $department->code.' - ' : '').$department->name)
                : $departmentLabel;
        }

        $data = array_merge([
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Stores Withdrawal Slip Register',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'department' => $departmentLabel,
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $this->swsRegisterRows(
                $validated['date_from'],
                $validated['date_to'],
                $validated['department_id'] ?? null
            ),
        ], $this->reportSignatories($request));

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-sws-register-%s.xlsx', now()->format('Ymd-His'));

            return (new ImSwsRegisterSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-sws-register-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-sws-register', $data, $filename);
    }

    public function transferRegister(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'ts_type' => ['required', 'in:'.implode(',', array_keys(self::TS_TYPES))],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $data = array_merge([
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Transfer Slip Register',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'ts_type_label' => self::TS_TYPES[$validated['ts_type']],
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $this->transferRegisterRows(
                $validated['date_from'],
                $validated['date_to'],
                $validated['ts_type']
            ),
        ], $this->reportSignatories($request));

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-transfer-register-%s.xlsx', now()->format('Ymd-His'));

            return (new ImTransferRegisterSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-transfer-register-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-transfer-register', $data, $filename);
    }

    public function deliveryRegister(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        $data = array_merge([
            'company' => PdfReport::DEFAULT_COMPANY,
            'title' => 'Delivery Receipt Register',
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'printed_at' => now()->format('d-m-Y H:i'),
            'rows' => $this->deliveryRegisterRows($validated['date_from'], $validated['date_to']),
        ], $this->reportSignatories($request));

        if ($validated['format'] === 'excel') {
            $filename = sprintf('im-delivery-register-%s.xlsx', now()->format('Ymd-His'));

            return (new ImDeliveryRegisterSpreadsheet($data))->download($filename);
        }

        $filename = sprintf('im-delivery-register-%s.pdf', now()->format('Ymd-His'));

        return PdfReport::analytical('pdf.reports.im-delivery-register', $data, $filename);
    }

    /**
     * @return Collection<int, array{item_code: string, item_name: string, unit: string|null, rows: Collection<int, array{doc_date: string, doc_type: string, doc_number: string, quantity: float}>}>
     */
    private function transactionGroups(string $dateFrom, string $dateTo, string $category): Collection
    {
        $rr = DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->whereDate('rr.received_date', '>=', $dateFrom)
            ->whereDate('rr.received_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('rri.qty_good', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'rr.received_date as doc_date',
                DB::raw("'RR' as doc_type"),
                'rr.rr_number as doc_number',
                'rri.qty_good as quantity',
                'u.name as unit',
            ]);

        $ts = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->join('items as i', 'i.id', '=', 'tsi.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereDate('ts.ts_date', '>=', $dateFrom)
            ->whereDate('ts.ts_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('tsi.quantity', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'ts.ts_date as doc_date',
                DB::raw("'TS' as doc_type"),
                'ts.ts_number as doc_number',
                'tsi.quantity as quantity',
                'u.name as unit',
            ]);

        $dr = DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->join('items as i', 'i.id', '=', 'di.item_id')
            ->join('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->whereDate('d.dr_date', '>=', $dateFrom)
            ->whereDate('d.dr_date', '<=', $dateTo)
            ->where('ic.name', $category)
            ->where('di.quantity', '>', 0)
            ->select([
                'i.code as item_code',
                'i.name as item_name',
                'd.dr_date as doc_date',
                DB::raw("'DR' as doc_type"),
                'd.dr_number as doc_number',
                'di.quantity as quantity',
                DB::raw('COALESCE(u.name, di.uom) as unit'),
            ]);

        $typeOrder = ['RR' => 1, 'TS' => 2, 'DR' => 3];

        return collect($rr->unionAll($ts)->unionAll($dr)->get())
            ->map(fn ($row) => [
                'item_code' => (string) $row->item_code,
                'item_name' => (string) $row->item_name,
                'doc_date' => Carbon::parse($row->doc_date)->toDateString(),
                'doc_type' => (string) $row->doc_type,
                'doc_number' => (string) $row->doc_number,
                'quantity' => (float) $row->quantity,
                'unit' => $row->unit ?: null,
            ])
            ->groupBy('item_code')
            ->sortKeys()
            ->map(function (Collection $itemRows) use ($typeOrder) {
                $first = $itemRows->first();
                $sorted = $itemRows
                    ->sortBy([
                        ['doc_date', 'asc'],
                        fn ($row) => $typeOrder[$row['doc_type']] ?? 99,
                        ['doc_number', 'asc'],
                    ])
                    ->values()
                    ->map(fn (array $row) => [
                        'doc_date' => $row['doc_date'],
                        'doc_type' => $row['doc_type'],
                        'doc_number' => $row['doc_number'],
                        'quantity' => $row['quantity'],
                    ]);

                return [
                    'item_code' => $first['item_code'],
                    'item_name' => $first['item_name'],
                    'unit' => $first['unit'],
                    'rows' => $sorted,
                ];
            })
            ->values();
    }

    /**
     * @return array{prepared_by_name: string, prepared_by_title: string, checked_by_name: string, checked_by_title: string, approved_by_name: string, approved_by_title: string}
     */
    private function reportSignatories(Request $request): array
    {
        return [
            'prepared_by_name' => $request->user()?->name ?? '',
            'prepared_by_title' => '',
            'checked_by_name' => 'Daniel Watuna',
            'checked_by_title' => 'IM Supervisor',
            'approved_by_name' => 'Rommy Tendean',
            'approved_by_title' => 'IM Manager',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function receivingRegisterRows(string $dateFrom, string $dateTo): Collection
    {
        return DB::table('receiving_report_items as rri')
            ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'rri.purchase_order_item_id')
            ->join('purchase_orders as po', 'po.id', '=', 'rr.purchase_order_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->join('items as i', 'i.id', '=', 'poi.item_id')
            ->leftJoin('item_categories as ic', 'ic.id', '=', 'i.category_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoin('prs_items as pri', 'pri.id', '=', 'poi.prs_item_id')
            ->leftJoin('users as canvasser', 'canvasser.id', '=', 'pri.canvasser_id')
            ->leftJoin('prs as pr', 'pr.id', '=', 'pri.prs_id')
            ->leftJoin('departments as d', 'd.id', '=', 'pr.department_id')
            ->whereNull('rr.deleted_at')
            ->whereNull('rri.deleted_at')
            ->whereDate('rr.received_date', '>=', $dateFrom)
            ->whereDate('rr.received_date', '<=', $dateTo)
            ->orderBy('rr.received_date')
            ->orderBy('rr.rr_number')
            ->orderBy('i.code')
            ->select([
                'rr.rr_number',
                'rr.received_date as rr_date',
                's.name as supplier_name',
                'i.code as item_code',
                'i.name as item_name',
                'ic.name as item_category',
                'u.name as unit',
                'rri.qty_good',
                'rri.qty_bad',
                'po.po_number',
                'po.created_at as po_created_at',
                'po.term_of_payment',
                'po.term_of_payment_type',
                'canvasser.name as canvasser_name',
                'd.code as end_user_code',
                'rr.notes',
            ])
            ->get()
            ->map(function ($row) {
                $paymentParts = array_filter([
                    $row->term_of_payment,
                    $row->term_of_payment_type,
                ]);

                return [
                    'rr_number' => (string) $row->rr_number,
                    'rr_date' => Carbon::parse($row->rr_date)->toDateString(),
                    'from' => (string) ($row->supplier_name ?? ''),
                    'item_code' => (string) $row->item_code,
                    'item_name' => (string) $row->item_name,
                    'item_category' => (string) ($row->item_category ?? ''),
                    'unit' => $row->unit ?: null,
                    'qty_good' => (float) $row->qty_good,
                    'qty_bad' => (float) $row->qty_bad,
                    'po_number' => (string) ($row->po_number ?? ''),
                    'po_date' => $row->po_created_at
                        ? Carbon::parse($row->po_created_at)->toDateString()
                        : null,
                    'payment_term' => $paymentParts === [] ? '' : implode(' / ', $paymentParts),
                    'canvasser' => (string) ($row->canvasser_name ?? ''),
                    'end_user' => (string) ($row->end_user_code ?? ''),
                    'remarks' => (string) ($row->notes ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function swsRegisterRows(string $dateFrom, string $dateTo, ?int $departmentId): Collection
    {
        $query = DB::table('store_withdrawal_items as swi')
            ->join('store_withdrawals as sw', 'sw.id', '=', 'swi.store_withdrawal_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('items as i', 'i.id', '=', 'swi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'sw.created_by')
            ->whereNull('sw.deleted_at')
            ->whereNull('swi.deleted_at')
            ->whereDate('sw.sws_date', '>=', $dateFrom)
            ->whereDate('sw.sws_date', '<=', $dateTo);

        if ($departmentId !== null) {
            $query->where('sw.department_id', $departmentId);
        }

        return $query
            ->orderBy('sw.sws_date')
            ->orderBy('sw.sws_number')
            ->orderBy('i.code')
            ->select([
                'sw.sws_number',
                'sw.sws_date',
                'sw.department_code',
                'd.name as department_name',
                'i.code as item_code',
                'swi.product_code',
                'i.name as item_name',
                'swi.uom',
                'u.name as unit_name',
                'swi.stock_on_hand_snapshot',
                'swi.quantity',
                'creator.name as creator_name',
                'sw.info',
            ])
            ->get()
            ->map(function ($row) {
                $deptCode = $row->department_code ?: '';
                $deptName = $row->department_name ?: '';
                $department = trim($deptCode.($deptCode !== '' && $deptName !== '' ? ' - ' : '').$deptName);

                return [
                    'sws_number' => (string) $row->sws_number,
                    'sws_date' => Carbon::parse($row->sws_date)->toDateString(),
                    'department' => $department,
                    'item_code' => (string) ($row->item_code ?: $row->product_code ?: ''),
                    'item_name' => (string) ($row->item_name ?? ''),
                    'unit' => $row->unit_name ?: ($row->uom ?: null),
                    'stock_on_hand' => (float) $row->stock_on_hand_snapshot,
                    'request_qty' => (float) $row->quantity,
                    'creator' => (string) ($row->creator_name ?? ''),
                    'info' => (string) ($row->info ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function transferRegisterRows(string $dateFrom, string $dateTo, string $tsType): Collection
    {
        $query = DB::table('transfer_slip_items as tsi')
            ->join('transfer_slips as ts', 'ts.id', '=', 'tsi.transfer_slip_id')
            ->leftJoin('store_withdrawals as sw', 'sw.id', '=', 'ts.store_withdrawal_id')
            ->leftJoin('departments as d', 'd.id', '=', 'sw.department_id')
            ->leftJoin('store_withdrawal_items as swi', 'swi.id', '=', 'tsi.store_withdrawal_item_id')
            ->leftJoin('items as i', 'i.id', '=', 'tsi.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'ts.created_by')
            ->whereNull('ts.deleted_at')
            ->whereNull('tsi.deleted_at')
            ->whereDate('ts.ts_date', '>=', $dateFrom)
            ->whereDate('ts.ts_date', '<=', $dateTo);

        if ($tsType === 'normal') {
            $query->where('ts.for_production', false);
        } elseif ($tsType === 'others') {
            $query->where('ts.for_production', true);
        }

        return $query
            ->orderBy('ts.ts_date')
            ->orderBy('ts.ts_number')
            ->orderBy('i.code')
            ->select([
                'ts.ts_number',
                'ts.ts_date',
                'sw.sws_number',
                'ts.for_production',
                'sw.department_code',
                'd.name as department_name',
                'i.code as item_code',
                'tsi.product_code',
                'i.name as item_name',
                'swi.uom',
                'u.name as unit_name',
                'swi.quantity as request_qty',
                'tsi.quantity as transfer_qty',
                'creator.name as creator_name',
                'ts.remarks',
            ])
            ->get()
            ->map(function ($row) {
                $deptCode = $row->department_code ?: '';
                $deptName = $row->department_name ?: '';
                $department = trim($deptCode.($deptCode !== '' && $deptName !== '' ? ' - ' : '').$deptName);

                return [
                    'ts_number' => (string) $row->ts_number,
                    'ts_date' => Carbon::parse($row->ts_date)->toDateString(),
                    'sws_number' => (string) ($row->sws_number ?? ''),
                    'ts_type' => $row->for_production ? 'Others' : 'Normal',
                    'to_department' => $department,
                    'item_code' => (string) ($row->item_code ?: $row->product_code ?: ''),
                    'item_name' => (string) ($row->item_name ?? ''),
                    'unit' => $row->unit_name ?: ($row->uom ?: null),
                    'request_qty' => (float) ($row->request_qty ?? 0),
                    'transfer_qty' => (float) $row->transfer_qty,
                    'creator' => (string) ($row->creator_name ?? ''),
                    'info' => (string) ($row->remarks ?? ''),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function deliveryRegisterRows(string $dateFrom, string $dateTo): Collection
    {
        return DB::table('delivery_items as di')
            ->join('deliveries as d', 'd.id', '=', 'di.delivery_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->leftJoin('items as i', 'i.id', '=', 'di.item_id')
            ->leftJoin('unit_of_measures as u', 'u.id', '=', 'i.unit_of_measure_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'd.created_by')
            ->whereNull('d.deleted_at')
            ->whereNull('di.deleted_at')
            ->whereDate('d.dr_date', '>=', $dateFrom)
            ->whereDate('d.dr_date', '<=', $dateTo)
            ->orderBy('d.dr_date')
            ->orderBy('d.dr_number')
            ->orderBy('i.code')
            ->select([
                'd.dr_number',
                'd.dr_date',
                'd.from_name',
                's.name as to_name',
                'i.code as item_code',
                'di.product_code',
                'i.name as item_name',
                'di.uom',
                'u.name as unit_name',
                'di.quantity',
                'creator.name as creator_name',
                'd.remarks',
            ])
            ->get()
            ->map(fn ($row) => [
                'dr_number' => (string) $row->dr_number,
                'dr_date' => Carbon::parse($row->dr_date)->toDateString(),
                'from' => (string) ($row->from_name ?? ''),
                'to' => (string) ($row->to_name ?? ''),
                'item_code' => (string) ($row->item_code ?: $row->product_code ?: ''),
                'item_name' => (string) ($row->item_name ?? ''),
                'unit' => $row->unit_name ?: ($row->uom ?: null),
                'quantity' => (float) $row->quantity,
                'creator' => (string) ($row->creator_name ?? ''),
                'remarks' => (string) ($row->remarks ?? ''),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, code: string, unit: string|null, beginning: float, rr: float, ts: float, dr: float, ending: float}>
     */
    private function stockInventoryRows(string $asOf, string $category): Collection
    {
        $monthStart = Carbon::parse($asOf)->startOfMonth()->toDateString();

        $items = Item::query()
            ->with(['unit:id,name'])
            ->whereHas('category', function ($query) use ($category) {
                $query->where('name', $category);
            })
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'unit_of_measure_id']);

        if ($items->isEmpty()) {
            return collect();
        }

        $itemIds = $items->pluck('id');

        $beginnings = $this->stockBalanceBeginnings($itemIds, $monthStart, $asOf);
        $movements = $this->stockBalanceMovements($itemIds, $monthStart, $asOf);
        $endings = $this->stockBalanceEndings($itemIds, $asOf);
        $inventoryBalances = DB::table('stock_inventories')
            ->whereIn('item_id', $itemIds)
            ->where('is_delete', false)
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(balance), 0) as balance')
            ->pluck('balance', 'item_id');

        return $items
            ->map(function (Item $item) use ($beginnings, $movements, $endings, $inventoryBalances) {
                $movement = $movements->get($item->id);
                $beginning = (float) ($beginnings[$item->id] ?? 0);
                $rr = (float) ($movement->rr ?? 0);
                $ts = (float) ($movement->ts ?? 0);
                $dr = (float) ($movement->dr ?? 0);
                $ending = $endings->has($item->id)
                    ? (float) $endings->get($item->id)
                    : (float) ($inventoryBalances[$item->id] ?? 0);

                return [
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit?->name,
                    'beginning' => $beginning,
                    'rr' => $rr,
                    'ts' => $ts,
                    'dr' => $dr,
                    'ending' => $ending,
                ];
            })
            ->filter(function (array $row) {
                return $row['beginning'] != 0
                    || $row['rr'] != 0
                    || $row['ts'] != 0
                    || $row['dr'] != 0
                    || $row['ending'] != 0;
            })
            ->values();
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, float>
     */
    private function stockBalanceBeginnings(Collection $itemIds, string $monthStart, string $asOf): Collection
    {
        $endColumn = DB::getQueryGrammar()->wrap('end');
        $beginColumn = DB::getQueryGrammar()->wrap('begin');

        $priorEnds = DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$endColumn} as ending_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date DESC, id DESC) as rn")
                    ->whereIn('item_id', $itemIds)
                    ->whereDate('date', '<', $monthStart),
                'ranked'
            )
            ->where('rn', 1)
            ->get();

        $beginnings = $priorEnds
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('ending_qty'));

        $missingItemIds = $itemIds->reject(fn ($id) => $beginnings->has($id))->values();

        if ($missingItemIds->isEmpty()) {
            return $beginnings;
        }

        $monthBegins = DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$beginColumn} as beginning_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date ASC, id ASC) as rn")
                    ->whereIn('item_id', $missingItemIds)
                    ->whereDate('date', '>=', $monthStart)
                    ->whereDate('date', '<=', $asOf),
                'ranked'
            )
            ->where('rn', 1)
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('beginning_qty'));

        return $beginnings->union($monthBegins);
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, object{rr: float|int|string, ts: float|int|string, dr: float|int|string}>
     */
    private function stockBalanceMovements(Collection $itemIds, string $monthStart, string $asOf): Collection
    {
        return DB::table('stock_balances')
            ->whereIn('item_id', $itemIds)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $asOf)
            ->groupBy('item_id')
            ->selectRaw('item_id, COALESCE(SUM(qty_in1), 0) as rr, COALESCE(SUM(qty_out1), 0) as ts, COALESCE(SUM(qty_out3), 0) as dr')
            ->get()
            ->keyBy('item_id');
    }

    /**
     * @param  Collection<int, int|string>  $itemIds
     * @return Collection<int|string, float>
     */
    private function stockBalanceEndings(Collection $itemIds, string $asOf): Collection
    {
        $endColumn = DB::getQueryGrammar()->wrap('end');

        return DB::query()
            ->fromSub(
                DB::table('stock_balances')
                    ->selectRaw("item_id, wh_code, {$endColumn} as ending_qty, ROW_NUMBER() OVER (PARTITION BY item_id, wh_code ORDER BY date DESC, id DESC) as rn")
                    ->whereIn('item_id', $itemIds)
                    ->whereDate('date', '<=', $asOf),
                'ranked'
            )
            ->where('rn', 1)
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('ending_qty'));
    }

    private function exportReport(
        string $format,
        string $excelView,
        array $data,
        string $filePrefix,
        string $pdfView
    ): Response {
        if ($format === 'excel') {
            return $this->streamExcel($filePrefix, $excelView, $data);
        }

        $filename = sprintf('%s-%s.pdf', $filePrefix, now()->format('Ymd-His'));

        return PdfReport::analytical($pdfView, $data, $filename);
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

    private function reportNotReady(): RedirectResponse
    {
        return back()->with('success', 'Report generation will be available soon.');
    }
}
