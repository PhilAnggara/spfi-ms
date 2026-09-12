<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing([
            'user:id,name,username',
            'conversation.participants',
        ]);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];

        $conversation = $this->message->conversation;

        if ($conversation?->isSupport()) {
            $channels[] = new PrivateChannel('chat.support');

            $supportUserId = (int) $conversation->support_user_id;
            if ($supportUserId > 0 && $supportUserId !== (int) $this->message->user_id) {
                $channels[] = new PrivateChannel('App.Models.User.'.$supportUserId);
            }

            return $channels;
        }

        $recipientIds = $conversation
            ? $conversation->participants
                ->pluck('user_id')
                ->reject(fn ($userId): bool => (int) $userId === (int) $this->message->user_id)
                ->values()
            : collect();

        foreach ($recipientIds as $recipientId) {
            $channels[] = new PrivateChannel('App.Models.User.'.$recipientId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toChatPayload(),
        ];
    }
}
