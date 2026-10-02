<?php

namespace App\Models;

use App\Enums\MessagePersona;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'persona',
        'body',
        'type',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'attachment_width',
        'attachment_height',
        'delivered_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'persona' => MessagePersona::class,
            'attachment_size' => 'integer',
            'attachment_width' => 'integer',
            'attachment_height' => 'integer',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachmentUrl(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        // Host-relative so media works when browsing via localhost, LAN IP, or any APP_URL mismatch.
        return '/storage/'.ltrim(str_replace('\\', '/', $this->attachment_path), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatPayload(?ConversationParticipant $viewerParticipant = null, ?ConversationParticipant $peerParticipant = null): array
    {
        $status = 'sent';
        $deliveredAt = $this->delivered_at;
        $readAt = $this->read_at;

        if ($readAt) {
            $status = 'read';
        } elseif ($deliveredAt) {
            $status = 'delivered';
        } elseif ($peerParticipant?->last_read_at && $peerParticipant->last_read_at->gte($this->created_at)) {
            $status = 'read';
            $readAt = $peerParticipant->last_read_at;
            $deliveredAt = $deliveredAt
                ?? $peerParticipant->last_delivered_at
                ?? $peerParticipant->last_read_at;
        } elseif ($peerParticipant?->last_delivered_at && $peerParticipant->last_delivered_at->gte($this->created_at)) {
            $status = 'delivered';
            $deliveredAt = $peerParticipant->last_delivered_at;
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'persona' => ($this->persona ?? MessagePersona::User)->value,
            'body' => $this->body,
            'type' => $this->type->value,
            'attachment_url' => $this->attachmentUrl(),
            'attachment_original_name' => $this->attachment_original_name,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size' => $this->attachment_size,
            'attachment_width' => $this->attachment_width,
            'attachment_height' => $this->attachment_height,
            'status' => $status,
            'delivered_at' => $deliveredAt?->toIso8601String(),
            'read_at' => $readAt?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
            ] : null,
        ];
    }
}
