<?php

namespace App\Notifications;

use App\Models\Prs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Hapus "implements ShouldQueue" untuk development agar notifikasi langsung terkirim
// Untuk production, aktifkan kembali dan gunakan queue worker
class PrsSubmittedNotification extends Notification // implements ShouldQueue
{
    use Queueable;

    /**
     * Instance PRS yang baru dibuat
     */
    public function __construct(public Prs $prs)
    {
    }

    /**
     * Channel yang digunakan untuk notifikasi
     * 'database' = In-App notification (disimpan di tabel notifications)
     * 'mail' = Email notification (opsional, bisa diaktifkan nanti)
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Bisa ditambah 'mail' jika ingin kirim email juga
    }

    /**
     * Format Email Notification (opsional)
     * Uncomment jika ingin menggunakan email notification
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->subject('New PRS Submitted - ' . $this->prs->prs_number)
    //         ->greeting('Hello ' . $notifiable->name)
    //         ->line('A new Purchase Requisition Slip has been submitted for your review.')
    //         ->line('PRS Number: ' . $this->prs->prs_number)
    //         ->line('Department: ' . $this->prs->department->name)
    //         ->line('Requested By: ' . $this->prs->user->name)
    //         ->line('Date Needed: ' . $this->prs->date_needed->format('d M Y'))
    //         ->action('Review PRS', url('/prs/' . $this->prs->id))
    //         ->line('Thank you for using SPFI-MS!');
    // }

    /**
     * Data yang disimpan ke database notifications
     * Data ini akan ditampilkan di notification dropdown
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'prs_submitted',
            'prs_id' => $this->prs->id,
            'prs_number' => $this->prs->prs_number,
            'title' => 'New PRS Submitted',
            'message' => 'PRS #' . $this->prs->prs_number . ' has been submitted by ' . $this->prs->user->name,
            'department' => $this->prs->department->name,
            'requester' => $this->prs->user->name,
            'items_count' => $this->prs->items->count(),
            'action_url' => '/prs',
            'icon' => 'bi-file-earmark-plus',
            'icon_color' => 'bg-primary',
        ];
    }
}
