<?php

namespace App\Services;

use App\Enums\ScreenMessageAudienceType;
use App\Enums\ScreenMessageDisplayMode;
use App\Enums\ScreenMessageTargetType;
use App\Enums\ScreenMessageTheme;
use App\Events\ScreenMessageDeactivated;
use App\Events\ScreenMessageSent;
use App\Models\ScreenMessage;
use App\Models\ScreenMessageRecipient;
use App\Models\ScreenMessageReply;
use App\Models\ScreenMessageTarget;
use App\Models\User;
use App\Support\ScreenMessageAccess;
use App\Support\ScreenMessageHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ScreenMessageService
{
    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     display_mode: string,
     *     duration_seconds?: int|null,
     *     audience_type: string,
     *     theme?: string,
     *     allow_reply?: bool,
     *     target_ids?: list<int>
     * }  $data
     */
    public function create(User $sender, array $data): ScreenMessage
    {
        $displayMode = ScreenMessageDisplayMode::from($data['display_mode']);
        $audienceType = ScreenMessageAudienceType::from($data['audience_type']);
        $theme = ScreenMessageTheme::tryFrom((string) ($data['theme'] ?? ''))
            ?? ScreenMessageTheme::Default;

        if ($displayMode->isPermanent() && ! ScreenMessageAccess::canCreatePermanent($sender)) {
            throw ValidationException::withMessages([
                'display_mode' => 'You do not have permission to create permanent screen messages.',
            ]);
        }

        if (! ScreenMessageAccess::canTargetAudience($sender, $audienceType)) {
            throw ValidationException::withMessages([
                'audience_type' => 'You do not have permission to target this audience.',
            ]);
        }

        $targetIds = array_values(array_unique(array_map('intval', $data['target_ids'] ?? [])));
        $recipients = $this->resolveRecipients($sender, $audienceType, $targetIds);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'target_ids' => 'No recipients matched the selected audience.',
            ]);
        }

        $duration = $displayMode->isPermanent()
            ? null
            : (isset($data['duration_seconds']) && $data['duration_seconds'] !== '' && $data['duration_seconds'] !== null
                ? (int) $data['duration_seconds']
                : null);

        if ($duration !== null && $duration < 1) {
            $duration = null;
        }

        /** @var ScreenMessage $message */
        $message = DB::transaction(function () use ($sender, $data, $displayMode, $audienceType, $theme, $duration, $targetIds, $recipients): ScreenMessage {
            $message = ScreenMessage::query()->create([
                'user_id' => $sender->id,
                'title' => $data['title'],
                'body' => ScreenMessageHtml::sanitize((string) $data['body']),
                'display_mode' => $displayMode,
                'duration_seconds' => $duration,
                'audience_type' => $audienceType,
                'allow_reply' => (bool) ($data['allow_reply'] ?? false),
                'theme' => $theme,
                'is_active' => true,
            ]);

            if ($audienceType === ScreenMessageAudienceType::Users) {
                foreach ($targetIds as $targetId) {
                    ScreenMessageTarget::query()->create([
                        'screen_message_id' => $message->id,
                        'target_type' => ScreenMessageTargetType::User,
                        'target_id' => $targetId,
                    ]);
                }
            }

            if ($audienceType === ScreenMessageAudienceType::Departments) {
                foreach ($targetIds as $targetId) {
                    ScreenMessageTarget::query()->create([
                        'screen_message_id' => $message->id,
                        'target_type' => ScreenMessageTargetType::Department,
                        'target_id' => $targetId,
                    ]);
                }
            }

            foreach ($recipients as $recipient) {
                ScreenMessageRecipient::query()->create([
                    'screen_message_id' => $message->id,
                    'user_id' => $recipient->id,
                ]);
            }

            return $message;
        });

        $message->load('recipients');

        foreach ($message->recipients as $recipient) {
            $this->safeBroadcast(new ScreenMessageSent($message, (int) $recipient->user_id));
        }

        return $message;
    }

    public function deactivate(ScreenMessage $message, User $actor): ScreenMessage
    {
        if (! $message->is_active) {
            return $message;
        }

        $message->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $actor->id,
        ])->save();

        $this->broadcastDeactivated($message);

        return $message->fresh() ?? $message;
    }

    public function delete(ScreenMessage $message, User $actor): void
    {
        if ($message->is_active) {
            $message->forceFill([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => $actor->id,
            ])->save();

            $this->broadcastDeactivated($message);
        }

        $message->delete();
    }

    public function markSeen(ScreenMessage $message, User $user): ?string
    {
        $recipient = $this->recipientFor($message, $user);
        $updates = [];

        if ($recipient->seen_at === null) {
            $updates['seen_at'] = now();
        }

        if (
            $recipient->overlay_expires_at === null
            && $message->duration_seconds
            && ! $message->display_mode->isPermanent()
        ) {
            $updates['overlay_expires_at'] = now()->addSeconds((int) $message->duration_seconds);
        }

        if ($updates !== []) {
            $recipient->forceFill($updates)->save();
        }

        return $recipient->fresh()?->overlay_expires_at?->toIso8601String();
    }

    public function dismiss(ScreenMessage $message, User $user): void
    {
        if ($message->display_mode->isPermanent()) {
            throw ValidationException::withMessages([
                'message' => 'Permanent messages cannot be dismissed by recipients.',
            ]);
        }

        if (! $message->is_active) {
            return;
        }

        $recipient = $this->recipientFor($message, $user);

        $recipient->forceFill([
            'seen_at' => $recipient->seen_at ?? now(),
            'dismissed_at' => now(),
        ])->save();
    }

    public function reply(ScreenMessage $message, User $user, string $body): ScreenMessageReply
    {
        if (! $message->allow_reply) {
            throw ValidationException::withMessages([
                'body' => 'Replies are not allowed for this message.',
            ]);
        }

        $this->recipientFor($message, $user);

        return ScreenMessageReply::query()->updateOrCreate(
            [
                'screen_message_id' => $message->id,
                'user_id' => $user->id,
            ],
            ['body' => $body],
        );
    }

    /**
     * @return Collection<int, ScreenMessage>
     */
    public function pendingFor(User $user): Collection
    {
        $this->dismissExpiredFor($user);

        return ScreenMessage::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $permanent) use ($user): void {
                    $permanent->where('display_mode', ScreenMessageDisplayMode::Permanent->value)
                        ->whereHas('recipients', fn (Builder $r) => $r->where('user_id', $user->id));
                })->orWhere(function (Builder $transient) use ($user): void {
                    $transient->where('display_mode', '!=', ScreenMessageDisplayMode::Permanent->value)
                        ->whereHas('recipients', function (Builder $r) use ($user): void {
                            $r->where('user_id', $user->id)->whereNull('dismissed_at');
                        });
                });
            })
            ->with([
                'recipients' => fn ($q) => $q->where('user_id', $user->id),
                'replies' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{
     *     seen_count: int,
     *     recipient_count: int,
     *     recipients: list<array<string, mixed>>,
     *     replies: list<array<string, mixed>>|null
     * }
     */
    public function livePayload(ScreenMessage $message, User $actor): array
    {
        $message->load([
            'recipients.user.department',
            'replies.user',
        ]);

        $recipients = $message->recipients
            ->sortBy(fn (ScreenMessageRecipient $recipient) => $recipient->user?->name ?? '')
            ->values()
            ->map(fn (ScreenMessageRecipient $recipient): array => [
                'id' => $recipient->id,
                'user_id' => $recipient->user_id,
                'name' => $recipient->user?->name ?? '—',
                'department' => $recipient->user?->department?->name ?? '—',
                'seen_at' => $recipient->seen_at?->toIso8601String(),
                'seen_at_label' => $recipient->seen_at?->format('d M Y H:i'),
                'dismissed_at' => $recipient->dismissed_at?->toIso8601String(),
                'dismissed_at_label' => $recipient->dismissed_at?->format('d M Y H:i'),
                'is_seen' => $recipient->seen_at !== null,
            ])
            ->all();

        $replies = null;
        if (ScreenMessageAccess::canViewReplies($actor, $message)) {
            $replies = $message->replies
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (ScreenMessageReply $reply): array => [
                    'id' => $reply->id,
                    'user_name' => $reply->user?->name ?? '—',
                    'body' => $reply->body,
                    'created_at' => $reply->created_at?->toIso8601String(),
                    'created_at_label' => $reply->created_at?->format('d M Y H:i'),
                ])
                ->all();
        }

        $seenCount = $message->recipients->whereNotNull('seen_at')->count();

        return [
            'seen_count' => $seenCount,
            'recipient_count' => $message->recipients->count(),
            'recipients' => $recipients,
            'replies' => $replies,
        ];
    }

    private function dismissExpiredFor(User $user): void
    {
        $expired = ScreenMessageRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->whereNotNull('overlay_expires_at')
            ->where('overlay_expires_at', '<=', now())
            ->whereHas('screenMessage', function (Builder $query): void {
                $query->where('is_active', true)
                    ->where('display_mode', '!=', ScreenMessageDisplayMode::Permanent->value);
            })
            ->get();

        foreach ($expired as $recipient) {
            $recipient->forceFill([
                'seen_at' => $recipient->seen_at ?? now(),
                'dismissed_at' => now(),
            ])->save();
        }
    }

    /**
     * @param  list<int>  $targetIds
     * @return Collection<int, User>
     */
    public function resolveRecipients(User $sender, ScreenMessageAudienceType $audienceType, array $targetIds): Collection
    {
        $query = User::query()->whereNull('deleted_at')->whereKeyNot($sender->id);

        if ($audienceType === ScreenMessageAudienceType::All) {
            if (! ScreenMessageAccess::canCreateForAll($sender)) {
                throw ValidationException::withMessages([
                    'audience_type' => 'You cannot send messages to all users.',
                ]);
            }

            return $query->orderBy('id')->get();
        }

        if ($audienceType === ScreenMessageAudienceType::Users) {
            if ($targetIds === []) {
                return collect();
            }

            $query->whereIn('id', $targetIds);

            if (! ScreenMessageAccess::canCreateForAll($sender)) {
                if (! $sender->department_id) {
                    throw ValidationException::withMessages([
                        'target_ids' => 'You must belong to a department to message department users.',
                    ]);
                }

                $query->where('department_id', $sender->department_id);
            }

            return $query->orderBy('id')->get();
        }

        if ($targetIds === []) {
            return collect();
        }

        if (! ScreenMessageAccess::canCreateForAll($sender)) {
            if (! $sender->department_id || ! in_array((int) $sender->department_id, $targetIds, true)) {
                throw ValidationException::withMessages([
                    'target_ids' => 'You may only target your own department.',
                ]);
            }

            $targetIds = [(int) $sender->department_id];
        }

        return $query->whereIn('department_id', $targetIds)->orderBy('id')->get();
    }

    /**
     * @return Builder<ScreenMessage>
     */
    public function scopedIndexQuery(User $actor): Builder
    {
        $query = ScreenMessage::query()->with(['user.department'])->latest('id');

        if ($actor->can('view-all-screen-messages')) {
            return $query;
        }

        $own = $actor->can('view-own-screen-messages');
        $department = $actor->can('view-department-screen-messages');

        return $query->where(function (Builder $builder) use ($actor, $own, $department): void {
            if ($own) {
                $builder->orWhere('user_id', $actor->id);
            }

            if ($department && $actor->department_id) {
                $builder->orWhereHas('user', function (Builder $userQuery) use ($actor): void {
                    $userQuery->where('department_id', $actor->department_id);
                });
            }

            if (! $own && ! $department) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    private function recipientFor(ScreenMessage $message, User $user): ScreenMessageRecipient
    {
        $recipient = ScreenMessageRecipient::query()
            ->where('screen_message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $recipient) {
            throw ValidationException::withMessages([
                'message' => 'You are not a recipient of this message.',
            ]);
        }

        return $recipient;
    }

    private function broadcastDeactivated(ScreenMessage $message): void
    {
        $recipientIds = ScreenMessageRecipient::query()
            ->where('screen_message_id', $message->id)
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            $this->safeBroadcast(new ScreenMessageDeactivated((int) $message->id, (int) $recipientId));
        }
    }

    private function safeBroadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $exception) {
            Log::warning('Screen message broadcast failed; recipients will still receive via pending poll.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
