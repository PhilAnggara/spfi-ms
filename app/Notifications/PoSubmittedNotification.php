<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PoSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PurchaseOrder $purchaseOrder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        // Keep payload small for in-app notification list.
        return [
            'type' => 'po_submitted',
            'po_id' => $this->purchaseOrder->id,
            'title' => 'New PO Submitted',
            'message' => 'PO draft submitted by ' . $this->purchaseOrder->createdBy?->name,
            'supplier' => $this->purchaseOrder->supplier?->name,
            'items_count' => $this->purchaseOrder->items()->count(),
            'action_url' => '/purchase-orders/approval',
            'icon' => 'bi-bag-check',
            'icon_color' => 'bg-success',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
