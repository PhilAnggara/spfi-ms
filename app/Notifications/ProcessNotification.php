<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProcessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => (string) ($this->payload['type'] ?? 'process_update'),
            'title' => (string) ($this->payload['title'] ?? 'Process Update'),
            'message' => (string) ($this->payload['message'] ?? 'There is an update in your workflow.'),
            'action_url' => (string) ($this->payload['action_url'] ?? '#'),
            'icon' => (string) ($this->payload['icon'] ?? 'fa-light fa-bell'),
            'icon_color' => (string) ($this->payload['icon_color'] ?? 'bg-primary'),
            'meta' => $this->payload['meta'] ?? [],
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
