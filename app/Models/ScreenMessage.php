<?php

namespace App\Models;

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Enums\ScreenMessageTheme;
use App\Support\ScreenMessageHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScreenMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ScreenMessageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'display_mode',
        'duration_seconds',
        'audience_type',
        'allow_reply',
        'theme',
        'is_active',
        'deactivated_at',
        'deactivated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_mode' => ScreenMessageDisplayMode::class,
            'audience_type' => ScreenMessageAudienceType::class,
            'theme' => ScreenMessageTheme::class,
            'duration_seconds' => 'integer',
            'allow_reply' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @return HasMany<ScreenMessageTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(ScreenMessageTarget::class);
    }

    /**
     * @return HasMany<ScreenMessageRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ScreenMessageRecipient::class);
    }

    /**
     * @return HasMany<ScreenMessageReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ScreenMessageReply::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toOverlayPayload(?User $viewer = null): array
    {
        $theme = $this->theme ?? ScreenMessageTheme::Default;

        $payload = [
            'id' => $this->id,
            'title' => $this->title,
            'body' => ScreenMessageHtml::sanitize($this->body),
            'display_mode' => $this->display_mode->value,
            'duration_seconds' => $this->duration_seconds,
            'allow_reply' => $this->allow_reply,
            'theme' => $theme->value,
            'theme_label' => $theme->label(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => null,
            'my_reply' => null,
        ];

        if ($viewer) {
            $recipient = $this->relationLoaded('recipients')
                ? $this->recipients->firstWhere('user_id', $viewer->id)
                : $this->recipients()->where('user_id', $viewer->id)->first();

            $payload['expires_at'] = $recipient?->overlay_expires_at?->toIso8601String();

            if ($this->allow_reply) {
                $reply = $this->relationLoaded('replies')
                    ? $this->replies->firstWhere('user_id', $viewer->id)
                    : $this->replies()->where('user_id', $viewer->id)->first();

                if ($reply) {
                    $payload['my_reply'] = [
                        'id' => $reply->id,
                        'body' => $reply->body,
                    ];
                }
            }
        }

        return $payload;
    }
}
