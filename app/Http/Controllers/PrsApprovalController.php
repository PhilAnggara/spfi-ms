<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use Illuminate\Http\Request;

class PrsApprovalController extends Controller
{
    /**
     * Display list of PRS pending approval
     */
    public function index()
    {
        $items = Prs::all()->sortDesc();
        return view('pages.prs-approval', [
            'items' => $items,
        ]);
    }
}
