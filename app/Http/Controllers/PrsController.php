<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Prs;
use App\Models\PrsItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PrsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Prs::all()->sortDesc();
        $depatments = Department::all();
        return view('pages.prs', [
            'items' => $items,
            'departments' => $depatments,
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
        //
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
