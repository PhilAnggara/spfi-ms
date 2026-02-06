<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use App\Models\User;
use Illuminate\Http\Request;

class PrsApprovalController extends Controller
{
    /**
     * Display list of PRS pending approval
     */
    public function index()
    {
        $items = Prs::with(['department', 'user', 'items', 'canvaser', 'logs' => function ($query) {
            $query->latest();
        }])->orderByDesc('id')->get();
        $canvasers = User::role('canvaser')->orderBy('name')->get();
        return view('pages.prs-approval', [
            'items' => $items,
            'canvasers' => $canvasers,
        ]);
    }

    /**
     * Hold a PRS with a reason.
     */
    public function hold(Request $request, Prs $prs)
    {
        if ($prs->status === 'ON_HOLD') {
            return redirect()->back()->withErrors(['message' => 'PRS is already on hold.']);
        }
        if ($prs->status === 'APPROVED') {
            return redirect()->back()->withErrors(['message' => 'Approved PRS cannot be held.']);
        }

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $previousStatus = $prs->status;
        $prs->status = 'ON_HOLD';
        $prs->save();

        $prs->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'HOLD',
            'message' => $data['message'],
            'meta' => [
                'previous_status' => $previousStatus,
            ],
        ]);

        return redirect()->back()->with('success', 'PRS has been put on hold.');
    }

    /**
     * Approve and assign a canvasser.
     */
    public function approve(Request $request, Prs $prs)
    {
        $data = $request->validate([
            'canvaser_id' => ['required', 'exists:users,id'],
        ]);

        $isCanvaser = User::role('canvaser')->where('id', $data['canvaser_id'])->exists();
        if (! $isCanvaser) {
            return redirect()->back()->withErrors(['canvaser_id' => 'Selected user is not a canvasser.']);
        }

        $previousStatus = $prs->status;
        $prs->status = 'CANVASING';
        $prs->canvaser_id = $data['canvaser_id'];
        $prs->save();

        $prs->logs()->create([
            'user_id' => $request->user()?->id,
            'action' => 'CANVASING',
            'message' => 'Approved and assigned canvasser.',
            'meta' => [
                'previous_status' => $previousStatus,
                'canvaser_id' => $data['canvaser_id'],
            ],
        ]);

        return redirect()->back()->with('success', 'PRS has been approved and assigned.');
    }
}
