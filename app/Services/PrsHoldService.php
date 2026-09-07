<?php

namespace App\Services;

use App\Models\Prs;
use App\Models\PrsItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PrsHoldService
{
    public function __construct(
        private NotificationRecipientService $notificationRecipientService
    ) {}

    public function holdByPurchasingManager(Prs $prs, User $actor, string $message): void
    {
        if ($prs->status === 'ON_HOLD') {
            throw ValidationException::withMessages(['message' => 'PRS is already on hold.']);
        }

        if ($prs->status === 'PO_CREATED') {
            throw ValidationException::withMessages(['message' => 'PRS with created PO cannot be held.']);
        }

        $hasLockedItems = $prs->items()
            ->where(function ($query) {
                $query->whereNotNull('purchase_order_id')
                    ->orWhere('is_direct_purchase', true);
            })
            ->exists();

        if ($hasLockedItems) {
            throw ValidationException::withMessages([
                'message' => 'PRS cannot be held because one or more items already have a PO or are marked as direct purchase.',
            ]);
        }

        $previousStatus = $prs->status;
        $prs->status = 'ON_HOLD';
        $prs->save();

        $prs->logs()->create([
            'user_id' => $actor->id,
            'action' => 'HOLD',
            'message' => $message,
            'meta' => [
                'previous_status' => $previousStatus,
                'held_by' => 'purchasing_manager',
            ],
        ]);

        $assignedCanvassers = collect();
        if (in_array($previousStatus, ['CANVASSING', 'CANVASSER_HOLD'], true)) {
            $assignedCanvassers = $prs->items()
                ->whereNotNull('canvasser_id')
                ->with('canvasser')
                ->get()
                ->pluck('canvasser')
                ->filter();
        }

        $recipients = $this->notificationRecipientService->uniqueUsers(
            collect([$prs->user])->filter(),
            $this->notificationRecipientService->purchasingManagers(),
            $assignedCanvassers
        );

        $this->notificationRecipientService->notify($recipients, [
            'type' => 'prs_on_hold',
            'title' => 'PRS On Hold',
            'message' => 'PRS #'.$prs->prs_number.' is on hold.',
            'action_url' => '/prs',
            'icon' => 'fa-light fa-circle-pause',
            'icon_color' => 'bg-warning',
            'meta' => [
                'prs_id' => $prs->id,
                'previous_status' => $previousStatus,
            ],
        ]);
    }

    public function holdByCanvasser(Prs $prs, PrsItem $prsItem, User $actor, string $message): void
    {
        if ($prsItem->canvasser_id !== $actor->id) {
            abort(403);
        }

        if ($prsItem->purchase_order_id) {
            throw ValidationException::withMessages(['message' => 'Cannot hold PRS because a PO has already been created for this item.']);
        }

        if (! $prs->isAvailableForCanvassing()) {
            throw ValidationException::withMessages(['message' => 'Only PRS in canvassing can be held by canvasser.']);
        }

        $previousStatus = $prs->status;
        $prs->status = 'CANVASSER_HOLD';
        $prs->save();

        $prs->logs()->create([
            'user_id' => $actor->id,
            'action' => 'CANVASSER_HOLD',
            'message' => $message,
            'meta' => [
                'previous_status' => $previousStatus,
                'held_by' => 'canvasser',
                'prs_item_id' => $prsItem->id,
                'canvasser_id' => $actor->id,
            ],
        ]);

        $recipients = $this->notificationRecipientService->uniqueUsers(
            collect([$prs->user])->filter(),
            $this->notificationRecipientService->purchasingManagers()
        );

        $this->notificationRecipientService->notify($recipients, [
            'type' => 'prs_canvasser_hold',
            'title' => 'PRS Needs Quantity Revision',
            'message' => 'PRS #'.$prs->prs_number.' needs quantity revision from canvasser feedback.',
            'action_url' => '/prs',
            'icon' => 'fa-light fa-circle-pause',
            'icon_color' => 'bg-warning',
            'meta' => [
                'prs_id' => $prs->id,
                'prs_item_id' => $prsItem->id,
                'previous_status' => $previousStatus,
            ],
        ]);
    }
}
