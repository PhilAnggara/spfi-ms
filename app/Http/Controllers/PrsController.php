<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\User;
use App\Notifications\PrsSubmittedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PrsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Prs::with(['department', 'user', 'items.item', 'items.canvaser', 'items.canvasingItems', 'items.selectedCanvasingItem', 'logs' => function ($query) {
            $query->latest();
        }])->orderByDesc('id')->get();
        $departments = Department::all();
        return view('pages.prs', [
            'items' => $items,
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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

        return redirect()->back()->with('success', 'New PRS has been created successfully.');
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

    // fungsi untuk genearate PRS Number
    private function generatePrsNumber($departmentID)
    {
        $user = Auth::user(); // ambil user yang sedang terautentikasi
        $departmentCode = Department::find($departmentID)->code; // ambil kode departemen dari relasi user->department
        // $departmentID = $user->department->id; // ambil ID departemen dari relasi user->department
        $year = date('y'); // ambil dua digit tahun saat ini (mis. "26")
        $month = date('m'); // ambil bulan saat ini dengan dua digit (mis. "01".."12")
        $day = date('d'); // ambil hari saat ini dengan dua digit (mis. "01".."31")
        $lastPrs = Prs::withTrashed()->where('department_id', $departmentID) // mulai query untuk mencari PRS terakhir berdasarkan department
            // ->whereMonth('created_at', date('m')) // (dinonaktifkan) filter berdasarkan bulan pembuatan jika diperlukan
            ->whereYear('created_at', date('Y')) // batasi hasil pada tahun berjalan
            ->orderBy('id', 'desc') // urutkan menurun berdasarkan id untuk mendapatkan entri terbaru
            ->first(); // ambil satu hasil pertama (terbaru)
        $lastNumber = $lastPrs ? (int) substr($lastPrs->prs_number, -3) : 0; // jika ada PRS terakhir, ambil 3 digit terakhir dari prs_number sebagai integer, jika tidak set 0
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT); // tambahkan 1 dan pad dengan nol di kiri hingga panjang 3 (mis. "001")

        return $departmentCode . '-' . $day . $month . $year . '-' . $newNumber; // bangun dan kembalikan format nomor PRS: DEPT-ddmmyy-###
    }
}
