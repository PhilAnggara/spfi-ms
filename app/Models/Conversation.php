<?php

namespace App\Models;

use App\Enums\ConversationType;
use App\Enums\SupportConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'direct_key',
        'support_user_id',
        'assigned_to',
        'support_status',
        'support_last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'support_status' => SupportConversationStatus::class,
            'support_last_read_at' => 'datetime',
        ];
    }

    public static function directKeyFor(int $userIdA, int $userIdB): string
    {
        $ids = [$userIdA, $userIdB];
        sort($ids);

        return hash('sha256', $ids[0].':'.$ids[1]);
    }

    public static function supportKeyFor(int $supportUserId): string
    {
        return hash('sha256', 'support:'.$supportUserId);
    }

    public function isSupport(): bool
    {
        return $this->type === ConversationType::Support;
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'last_delivered_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supportUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function hasParticipant(int $userId): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('user_id', $userId);
        }

        return $this->participants()->where('user_id', $userId)->exists();
    }

    public function otherParticipant(User $viewer): ?User
    {
        if ($this->relationLoaded('users')) {
            return $this->users->firstWhere('id', '!=', $viewer->id);
        }

        return $this->users()->where('users.id', '!=', $viewer->id)->first();
    }
}
