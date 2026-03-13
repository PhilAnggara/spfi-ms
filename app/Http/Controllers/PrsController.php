<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\User;
use App\Notifications\PrsSubmittedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PrsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $canViewAll = $user && $user->hasAnyRole([
            'administrator',
            'general-manager',
            'purchasing-manager',
            'purchasing-staff',
        ]);

        $filters = [
            'keyword' => trim((string) $request->query('keyword', '')),
            'status' => trim((string) $request->query('status', '')),
            'department' => trim((string) $request->query('department', '')),
            'prs_start' => trim((string) $request->query('prs_start', '')),
            'prs_end' => trim((string) $request->query('prs_end', '')),
            'needed_start' => trim((string) $request->query('needed_start', '')),
            'needed_end' => trim((string) $request->query('needed_end', '')),
        ];

        $items = $this->paginatePrsForSqlServer(
            canViewAll: $canViewAll,
            userId: $user?->id,
            filters: $filters,
            perPage: 10,
        );
        $departments = Department::all();
        $filterDepartments = $canViewAll ? $departments : collect();
        return view('pages.prs', [
            'items' => $items,
            'departments' => $departments,
            'filterDepartments' => $filterDepartments,
            'canFilterDepartment' => $canViewAll,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $departments = Department::all();
        $categories = ItemCategory::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $search = trim((string) $request->query('search'));
        $categoryId = trim((string) $request->query('category'));
        $searchTerms = collect(preg_split('/\s+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->values();

        $itemsQuery = Item::with(['unit', 'category'])
            ->select(['id', 'name', 'code', 'stock_on_hand', 'unit_of_measure_id', 'category_id'])
            ->where('is_active', true)
            ->when($searchTerms->isNotEmpty(), function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->where(function ($subQuery) use ($term) {
                        $subQuery
                            ->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                            ->orWhereRaw('LOWER(code) LIKE ?', ['%' . $term . '%']);
                    });
                }
            })
            ->when($categoryId !== '' && is_numeric($categoryId), function ($query) use ($categoryId) {
                $query->where('category_id', (int) $categoryId);
            })
            ->orderBy('name');

        $items = $itemsQuery
            ->paginate(36)
            ->withQueryString();

        if ($request->expectsJson() || $request->ajax()) {
            $transformedItems = $items->getCollection()->map(function ($item) {
                $categoryName = $item->category?->name ?? 'Uncategorized';
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'stock_on_hand' => $item->stock_on_hand,
                    'unit' => $item->unit?->name ?? 'PCS',
                    'category' => $categoryName,
                    'category_icon' => category_icon($categoryName),
                    'category_data' => category_data_attr($categoryName),
                ];
            })->values();

            return response()->json([
                'data' => $transformedItems,
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                ],
            ]);
        }

        return view('pages.prs-create', [
            'departments' => $departments,
            'categories' => $categories,
            'items' => $items,
            'search' => $search,
            'selectedCategory' => $categoryId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $data['prs_number'] = $this->generatePrsNumber($data['department_id']);
        $data['user_id'] = Auth::id();
        $data['prs_date'] = date('Y-m-d');

        // dd($data);
        $newPrs = Prs::create($data);

        foreach($data['prsItems'] as $prsItem) {
            PrsItem::create([
                'prs_id'       => $newPrs->id,
                'item_id'      => $prsItem['item_id'],
                'quantity'     => $prsItem['quantity'],
            ]);
        }

        // ===== KIRIM NOTIFIKASI =====
        // Ambil user yang memiliki role 'Purchasing Manager' atau permission 'approve prs'
        $purchasingManagers = User::role('purchasing-manager')->get();

        // Jika tidak ada role, coba cari berdasarkan permission
        if ($purchasingManagers->isEmpty()) {
            $purchasingManagers = User::permission('approve-prs')->get();
        }

        // Kirim notifikasi ke setiap Purchasing Manager
        foreach ($purchasingManagers as $manager) {
            $manager->notify(new PrsSubmittedNotification($newPrs));
        }

        return redirect()->route('prs.index')->with('success', 'New PRS has been created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $prs = Prs::findOrFail($id);

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'date_needed'   => ['required', 'date'],
            'remarks'       => ['nullable', 'string'],
            'prsItems'      => ['required', 'array', 'min:1'],
            'prsItems.*.item_id'  => ['required', 'exists:items,id'],
            'prsItems.*.quantity' => ['required', 'numeric', 'min:1'],
        ]);

        $shouldRegenerate = $prs->department_id != $validated['department_id'];

        $prs->department_id = $validated['department_id'];
        $prs->date_needed   = $validated['date_needed'];
        $prs->remarks       = $validated['remarks'] ?? null;

        if ($shouldRegenerate) {
            $prs->prs_number = $this->generatePrsNumber($validated['department_id']);
        }

        $previousStatus = $prs->status;
        if ($prs->status === 'ON_HOLD') {
            $prs->status = 'RESUBMITTED';
        }

        $prs->save();

        // Reset items then re-create based on submitted rows
        PrsItem::where('prs_id', $prs->id)->delete();

        foreach ($validated['prsItems'] as $itemRow) {
            PrsItem::create([
                'prs_id'   => $prs->id,
                'item_id'  => $itemRow['item_id'],
                'quantity' => $itemRow['quantity'],
            ]);
        }

        if ($previousStatus === 'ON_HOLD') {
            $prs->logs()->create([
                'user_id' => $request->user()?->id,
                'action' => 'RESUBMIT',
                'message' => 'PRS updated after hold.',
                'meta' => [
                    'previous_status' => $previousStatus,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'PRS has been updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Prs::findOrFail($id);
        $tile = $item->prs_no;
        $item->delete();
        // session()->flash('delete', 'PRS ' . $tile . ' has been deleted successfully.');
        return redirect()->back()->with('success', 'PRS ' . $tile . ' has been deleted successfully.');
    }

    /**
     * Print PRS document untuk diajukan ke GM untuk approval (tanda tangan)
     * Menghasilkan PDF yang siap dicetak dan ditandatangani
     */
    public function print(string $id)
    {
        // Ambil data PRS beserta relasi yang diperlukan
        $prs = Prs::with(['user', 'department', 'items.item'])->findOrFail($id);

        // ubah status jadi SUBMITTED
        if ($prs->status === 'DRAFT' || $prs->status === 'ON_HOLD') {
            $previousStatus = $prs->status;
            $prs->status = 'SUBMITTED';
            $prs->save();

            // Log perubahan status jika sebelumnya DRAFT atau ON_HOLD
            $prs->logs()->create([
                'user_id' => Auth::id(),
                'action' => 'SUBMIT',
                'message' => 'PRS submitted for GM approval.',
                'meta' => [
                    'previous_status' => $previousStatus,
                ],
            ]);
        }

        // Generate QR code sebagai SVG (tidak memerlukan Imagick)
        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->generate($prs->prs_number);
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCode);

        // Data yang dikirim ke view PDF
        $data = [
            'prs' => $prs,
            'qrCodeBase64' => $qrCodeBase64,
        ];

        // Generate nama file dengan format: PRS-NOMOR-TANGGAL.pdf
        // Contoh: PRS-PRD-250125-001-2025-01-25.pdf
        $filename = sprintf(
            'PRS-%s-%s.pdf',
            $prs->prs_number,
            now()->format('Y-m-d')
        );

        // Generate dan stream PDF untuk dicetak
        return Pdf::loadView('pdf.prs-for-approval', $data)
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    /**
     * Export PRS list to PDF by month range.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'start_month' => ['required', 'date_format:Y-m'],
            'end_month'   => ['required', 'date_format:Y-m', 'after_or_equal:start_month'],
        ]);

        $start = Carbon::createFromFormat('Y-m', $validated['start_month'])->startOfMonth();
        $end   = Carbon::createFromFormat('Y-m', $validated['end_month'])->endOfMonth();

        $prs = Prs::with(['department', 'items.item'])
            ->whereBetween('prs_date', [$start, $end])
            ->orderByDesc('prs_date')
            ->get();

        $data = [
            'prsList'      => $prs,
            'start'        => $start,
            'end'          => $end,
            'generated_at' => now(),
        ];

        $filename = sprintf('prs-%s-to-%s.pdf', $start->format('Ym'), $end->format('Ym'));

        return Pdf::loadView('pdf.prs-report', $data)
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }

    // Sinkron dengan sistem lama: {DEPTCODE}{#######}, urutan naik per department.
    private function generatePrsNumber($departmentID)
    {
        $departmentCode = strtoupper(trim((string) Department::findOrFail($departmentID)->code));

        $lastPrsNumber = Prs::withTrashed()
            ->where('department_id', $departmentID)
            ->orderByDesc('id')
            ->value('prs_number');

        $lastNumber = 0;
        if (is_string($lastPrsNumber)) {
            $upperLastPrsNumber = strtoupper($lastPrsNumber);
            $exactPattern = '/^' . preg_quote($departmentCode, '/') . '(\d+)$/';

            if (preg_match($exactPattern, $upperLastPrsNumber, $matches) === 1) {
                $lastNumber = (int) $matches[1];
            }
        }

        // Sequence selalu 7 digit agar konsisten: 0000001, 0000002, dst.
        $nextNumber = str_pad((string) ($lastNumber + 1), 7, '0', STR_PAD_LEFT);

        return $departmentCode . $nextNumber;
    }

    /**
     * SQL Server-compatible pagination without OFFSET/FETCH.
     */
    private function paginatePrsForSqlServer(bool $canViewAll, ?int $userId, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPage = max(1, (int) $currentPage);

        $baseQuery = Prs::query();
        if (! $canViewAll) {
            $baseQuery->where('user_id', $userId);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        $department = trim((string) ($filters['department'] ?? ''));
        $prsStart = trim((string) ($filters['prs_start'] ?? ''));
        $prsEnd = trim((string) ($filters['prs_end'] ?? ''));
        $neededStart = trim((string) ($filters['needed_start'] ?? ''));
        $neededEnd = trim((string) ($filters['needed_end'] ?? ''));

        if ($keyword !== '') {
            $baseQuery->where(function ($query) use ($keyword) {
                $query->where('prs_number', 'like', "%{$keyword}%")
                    ->orWhere('remarks', 'like', "%{$keyword}%")
                    ->orWhereHas('department', function ($departmentQuery) use ($keyword) {
                        $departmentQuery->where('name', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($status !== '') {
            // Untuk status delivery turunan, fallback ke status dasar agar tetap kompatibel query DB.
            $statusMap = [
                'DELIVERY_PENDING' => 'APPROVED',
                'PARTIAL_DELIVERY' => 'APPROVED',
                'DELIVERY_COMPLETE' => 'DELIVERY_COMPLETE',
            ];
            $statusToUse = $statusMap[$status] ?? $status;
            $baseQuery->where('status', $statusToUse);
        }

        if ($department !== '') {
            $baseQuery->whereHas('department', function ($query) use ($department) {
                $query->where('name', $department);
            });
        }

        if ($prsStart !== '') {
            $baseQuery->whereDate('prs_date', '>=', $prsStart);
        }
        if ($prsEnd !== '') {
            $baseQuery->whereDate('prs_date', '<=', $prsEnd);
        }

        if ($neededStart !== '') {
            $baseQuery->whereDate('date_needed', '>=', $neededStart);
        }
        if ($neededEnd !== '') {
            $baseQuery->whereDate('date_needed', '<=', $neededEnd);
        }

        $total = (clone $baseQuery)->count();
        $startRow = (($currentPage - 1) * $perPage) + 1;
        $endRow = $currentPage * $perPage;

        $rankedIdsQuery = (clone $baseQuery)
            ->selectRaw('id, ROW_NUMBER() OVER (ORDER BY id DESC) as row_num');

        $ids = DB::query()
            ->fromSub($rankedIdsQuery, 'ranked_prs')
            ->whereBetween('row_num', [$startRow, $endRow])
            ->orderBy('row_num')
            ->pluck('id')
            ->all();

        $collection = collect();

        if (! empty($ids)) {
            $itemsById = Prs::with([
                'department',
                'user',
                'items.item',
                'items.canvaser',
                'items.canvasingItems',
                'items.selectedCanvasingItem',
                'items.purchaseOrderItem.receivingReportItems',
                'logs' => function ($query) {
                    $query->latest();
                },
            ])->whereIn('id', $ids)->get()->keyBy('id');

            $collection = collect($ids)
                ->map(fn ($id) => $itemsById->get($id))
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
}
