<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'type',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'attachment_size' => 'integer',
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

        return Storage::disk('public')->url($this->attachment_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatPayload(?ConversationParticipant $viewerParticipant = null, ?ConversationParticipant $peerParticipant = null): array
    {
        $status = 'sent';

        if ($peerParticipant?->last_read_at && $peerParticipant->last_read_at->gte($this->created_at)) {
            $status = 'read';
        } elseif ($peerParticipant?->last_delivered_at && $peerParticipant->last_delivered_at->gte($this->created_at)) {
            $status = 'delivered';
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'type' => $this->type->value,
            'attachment_url' => $this->attachmentUrl(),
            'attachment_original_name' => $this->attachment_original_name,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size' => $this->attachment_size,
            'status' => $status,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->relationLoaded('user') && $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
            ] : null,
        ];
    }
}
