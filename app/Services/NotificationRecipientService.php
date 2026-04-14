<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\ProcessNotification;
use Illuminate\Support\Collection;

class NotificationRecipientService
{
    public function purchasingManagers(): Collection
    {
        $users = User::role('purchasing-manager')->get();

        if ($users->isEmpty()) {
            $approvePrs = User::permission('approve-prs')->get();
            $approvePo = User::permission('approve-po')->get();

            $users = $this->uniqueUsers($approvePrs, $approvePo);
        }

        return $users;
    }

    public function inventoryTeam(): Collection
    {
        return User::role(['administrator', 'im-manager', 'im-supervisor', 'im-staff'])->get();
    }

    public function uniqueUsers(Collection ...$collections): Collection
    {
        return collect($collections)
            ->flatMap(fn (Collection $users) => $users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values();
    }

    public function relatedPurchaseOrderUsers(PurchaseOrder $purchaseOrder): Collection
    {
        $purchaseOrder->loadMissing(['createdBy', 'approvedBy', 'certifiedBy', 'items.prsItem.prs.user']);

        $fromPrs = $purchaseOrder->items
            ->map(fn ($item) => $item->prsItem?->prs?->user)
            ->filter(fn ($user) => $user instanceof User);

        return $this->uniqueUsers(
            collect([$purchaseOrder->createdBy, $purchaseOrder->approvedBy, $purchaseOrder->certifiedBy])->filter(),
            $fromPrs
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notify(Collection $recipients, array $payload): void
    {
        foreach ($recipients as $recipient) {
            $recipient->notify(new ProcessNotification($payload));
        }
    }
}
