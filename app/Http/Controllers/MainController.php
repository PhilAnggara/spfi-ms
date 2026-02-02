<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function dashboard()
    {
        return view('pages.dashboard');
    }

    public function cekCsv()
    {
        $data = [];
        return response()->json($data);
    }
}
