<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PoSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public PurchaseOrder $purchaseOrder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
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
}
