<?php

namespace App\Http\Controllers;

use App\Models\Prs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrsApprovalController extends Controller
{
    /**
     * Display list of PRS pending approval
     */
    public function index()
    {
        $items = Prs::with(['department', 'user', 'items.item', 'items.canvaser', 'items.canvasingItems', 'items.selectedCanvasingItem', 'items.purchaseOrderItem.receivingReportItems', 'logs' => function ($query) {
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.prs_item_id' => ['required', 'distinct', 'exists:prs_items,id'],
            'items.*.canvaser_id' => ['required', 'exists:users,id'],
        ]);

        $assignments = collect($data['items'])->keyBy('prs_item_id');
        $assignedPrsItemIds = $prs->items()->pluck('id')->all();
        $invalidPrsItems = $assignments->keys()->diff($assignedPrsItemIds);
        if ($invalidPrsItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'One or more PRS items are invalid for this PRS.']);
        }

        $canvaserIds = $assignments->pluck('canvaser_id')->unique()->values();
        $validCanvaserIds = User::role('canvaser')->whereIn('id', $canvaserIds)->pluck('id');
        $invalidCanvasers = $canvaserIds->diff($validCanvaserIds);
        if ($invalidCanvasers->isNotEmpty()) {
            return redirect()->back()->withErrors(['items' => 'One or more selected users are not canvassers.']);
        }

        $previousStatus = $prs->status;

        DB::transaction(function () use ($prs, $assignments, $previousStatus, $request) {
            foreach ($assignments as $prsItemId => $row) {
                $prs->items()->whereKey($prsItemId)->update([
                    'canvaser_id' => $row['canvaser_id'],
                ]);
            }

            $prs->status = 'CANVASING';
            $prs->save();

            $prs->logs()->create([
                'user_id' => $request->user()?->id,
                'action' => 'CANVASING',
                'message' => 'Approved and assigned canvassers per item.',
                'meta' => [
                    'previous_status' => $previousStatus,
                    'assignments' => $assignments->values()->all(),
                ],
            ]);
        });

        return redirect()->back()->with('success', 'PRS has been approved and assigned.');
    }
}
