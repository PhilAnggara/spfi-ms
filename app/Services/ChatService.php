<?php

namespace App\Services;

use App\Enums\ChatSystemBroadcastAudience;
use App\Enums\ConversationType;
use App\Enums\MessagePersona;
use App\Enums\MessageType;
use App\Enums\SupportConversationStatus;
use App\Events\ChatTyping;
use App\Events\ConversationRead;
use App\Events\MessageDelivered;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Department;
use App\Models\Message;
use App\Models\Session;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public const SYSTEM_DISPLAY_NAME = 'SPFI-MS';

    public const SYSTEM_AVATAR_PATH = '/assets/images/system_profile.png';

    public const SYSTEM_USERNAME = 'spfi-ms';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listConversations(User $user): Collection
    {
        $this->findOrCreateSupportThread($user);

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->where(function ($query): void {
                $query->whereHas('messages')
                    ->orWhere('type', ConversationType::Support);
            })
            ->with([
                'latestMessage.user',
                'participants',
                'users' => fn ($query) => $query->with([
                    'department:id,name',
                    'sessions' => fn ($sessions) => $sessions->select('id', 'user_id', 'last_activity'),
                ]),
                'supportUser.department:id,name',
                'assignee:id,name,username',
            ])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->latestMessage?->created_at?->timestamp ?? 0)
            ->values();

        return $conversations->map(fn (Conversation $conversation): array => $this->conversationPayload($conversation, $user));
    }

    /**
     * Support inbox for operators (not limited to participation).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listSupportConversations(User $operator): Collection
    {
        $conversations = Conversation::query()
            ->where('type', ConversationType::Support)
            ->whereHas('messages')
            ->with([
                'latestMessage.user',
                'participants',
                'supportUser.department:id,name',
                'assignee:id,name,username',
            ])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->latestMessage?->created_at?->timestamp ?? $conversation->updated_at?->timestamp ?? 0)
            ->values();

        return $conversations->map(fn (Conversation $conversation): array => $this->conversationPayload($conversation, $operator, forOperatorInbox: true));
    }

    /**
     * @return array{conversation: Conversation, message: Message}
     */
    public function sendDirectMessage(
        User $sender,
        User $peer,
        ?string $body = null,
        ?UploadedFile $attachment = null,
    ): array {
        $conversation = $this->findOrCreateDirect($sender, $peer);
        $message = $this->sendMessage($conversation, $sender, $body, $attachment);

        return [
            'conversation' => $conversation->fresh([
                'participants',
                'users.department:id,name',
                'latestMessage.user',
            ]),
            'message' => $message,
        ];
    }

    public function broadcastTyping(User $from, User $to, bool $typing, ?int $conversationId = null): void
    {
        broadcast(new ChatTyping($from, $to->id, $typing, $conversationId))->toOthers();
    }

    public function findOrCreateDirect(User $authUser, User $peer): Conversation
    {
        if ($authUser->id === $peer->id) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot start a conversation with yourself.',
            ]);
        }

        $directKey = Conversation::directKeyFor($authUser->id, $peer->id);

        $conversation = Conversation::query()->where('direct_key', $directKey)->first();

        if ($conversation) {
            return $conversation->load(['participants', 'users.department:id,name', 'latestMessage.user']);
        }

        return DB::transaction(function () use ($authUser, $peer, $directKey): Conversation {
            $conversation = Conversation::query()->create([
                'type' => ConversationType::Direct,
                'direct_key' => $directKey,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $authUser->id],
                ['user_id' => $peer->id],
            ]);

            return $conversation->load(['participants', 'users.department:id,name', 'latestMessage.user']);
        });
    }

    /**
     * @return CursorPaginator<int, Message>
     */
    public function paginateMessages(Conversation $conversation, int $perPage = 30): CursorPaginator
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('user:id,name,username')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findOrCreateSupportThread(User $endUser): Conversation
    {
        $existing = Conversation::query()
            ->where('type', ConversationType::Support)
            ->where('support_user_id', $endUser->id)
            ->first();

        if ($existing) {
            return $existing->load(['participants', 'supportUser.department:id,name', 'latestMessage.user', 'assignee:id,name,username']);
        }

        try {
            return DB::transaction(function () use ($endUser): Conversation {
                $locked = Conversation::query()
                    ->where('type', ConversationType::Support)
                    ->where('support_user_id', $endUser->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked) {
                    return $locked->load(['participants', 'supportUser.department:id,name', 'latestMessage.user', 'assignee:id,name,username']);
                }

                $conversation = Conversation::query()->create([
                    'type' => ConversationType::Support,
                    'direct_key' => Conversation::supportKeyFor($endUser->id),
                    'support_user_id' => $endUser->id,
                    'support_status' => SupportConversationStatus::Open,
                ]);

                $conversation->participants()->create([
                    'user_id' => $endUser->id,
                ]);

                return $conversation->load(['participants', 'supportUser.department:id,name', 'latestMessage.user', 'assignee:id,name,username']);
            });
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            if (! $exception instanceof UniqueConstraintViolationException
                && ! str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw $exception;
            }

            $existing = Conversation::query()
                ->where('type', ConversationType::Support)
                ->where('support_user_id', $endUser->id)
                ->firstOrFail();

            return $existing->load(['participants', 'supportUser.department:id,name', 'latestMessage.user', 'assignee:id,name,username']);
        }
    }

    public function canAccessConversation(Conversation $conversation, User $user): bool
    {
        if ($conversation->hasParticipant($user->id)) {
            return true;
        }

        return $conversation->isSupport() && $user->can('chat-support-operate');
    }

    public function ensureCanAccess(Conversation $conversation, User $user): void
    {
        abort_unless($this->canAccessConversation($conversation, $user), 403);
    }

    /**
     * @return array{conversation: Conversation, message: Message}
     */
    public function sendSupportMessage(
        Conversation $conversation,
        User $actor,
        ?string $body = null,
        ?UploadedFile $attachment = null,
        MessagePersona $persona = MessagePersona::User,
    ): array {
        abort_unless($conversation->isSupport(), 422);

        if ($persona === MessagePersona::System) {
            abort_unless($actor->can('chat-support-operate'), 403);
        } else {
            abort_unless((int) $conversation->support_user_id === (int) $actor->id, 403);
        }

        $message = $this->sendMessage($conversation, $actor, $body, $attachment, $persona, skipParticipantCheck: true);

        if ($persona === MessagePersona::System && ! $conversation->assigned_to) {
            $conversation->forceFill([
                'assigned_to' => $actor->id,
                'support_status' => SupportConversationStatus::Open,
            ])->save();
        } elseif ($persona === MessagePersona::User) {
            $conversation->forceFill([
                'support_status' => SupportConversationStatus::Open,
            ])->save();
        }

        return [
            'conversation' => $conversation->fresh([
                'participants',
                'supportUser.department:id,name',
                'latestMessage.user',
                'assignee:id,name,username',
            ]),
            'message' => $message,
        ];
    }

    public function markSupportRead(Conversation $conversation, User $operator): Conversation
    {
        abort_unless($conversation->isSupport() && $operator->can('chat-support-operate'), 403);

        $now = now();
        $conversation->forceFill([
            'support_last_read_at' => $now,
            'assigned_to' => $conversation->assigned_to ?: $operator->id,
        ])->save();

        broadcast(new ConversationRead($conversation, $operator, $now))->toOthers();

        return $conversation->fresh([
            'participants',
            'supportUser.department:id,name',
            'latestMessage.user',
            'assignee:id,name,username',
        ]);
    }

    public function assignSupportConversation(Conversation $conversation, User $operator): Conversation
    {
        abort_unless($conversation->isSupport() && $operator->can('chat-support-operate'), 403);

        $conversation->forceFill([
            'assigned_to' => $operator->id,
            'support_status' => $conversation->support_status ?? SupportConversationStatus::Open,
        ])->save();

        return $conversation->fresh([
            'participants',
            'supportUser.department:id,name',
            'latestMessage.user',
            'assignee:id,name,username',
        ]);
    }

    public function updateSupportStatus(Conversation $conversation, User $operator, SupportConversationStatus $status): Conversation
    {
        abort_unless($conversation->isSupport() && $operator->can('chat-support-operate'), 403);

        $conversation->forceFill([
            'support_status' => $status,
            'assigned_to' => $conversation->assigned_to ?: $operator->id,
        ])->save();

        return $conversation->fresh([
            'participants',
            'supportUser.department:id,name',
            'latestMessage.user',
            'assignee:id,name,username',
        ]);
    }

    /**
     * Broadcast an official SPFI-MS message into each recipient's support thread.
     *
     * @param  list<int>  $targetIds
     * @return array{sent_count: int, recipient_count: int, audience: string}
     */
    public function broadcastSystemMessage(
        User $operator,
        ChatSystemBroadcastAudience $audience,
        array $targetIds = [],
        ?string $body = null,
        ?UploadedFile $attachment = null,
    ): array {
        abort_unless($operator->can('chat-support-operate'), 403);

        $recipients = $this->resolveBroadcastRecipients($operator, $audience, $targetIds);

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages([
                'audience' => 'No recipients found for this broadcast.',
            ]);
        }

        $trimmedBody = filled($body) ? trim($body) : null;
        $sharedAttachment = $this->storeBroadcastAttachment($attachment);

        if ($trimmedBody === null && $sharedAttachment === null) {
            throw ValidationException::withMessages([
                'body' => 'Please enter a message or attach a file.',
            ]);
        }

        $sentCount = 0;

        foreach ($recipients as $recipient) {
            $conversation = $this->findOrCreateSupportThread($recipient);
            $this->sendStoredSupportMessage(
                $conversation,
                $operator,
                MessagePersona::System,
                $trimmedBody,
                $sharedAttachment,
            );

            if (! $conversation->assigned_to) {
                $conversation->forceFill([
                    'assigned_to' => $operator->id,
                    'support_status' => SupportConversationStatus::Open,
                ])->save();
            }

            $sentCount++;
        }

        return [
            'sent_count' => $sentCount,
            'recipient_count' => $recipients->count(),
            'audience' => $audience->value,
        ];
    }

    /**
     * @param  list<int>  $targetIds
     * @return Collection<int, User>
     */
    public function resolveBroadcastRecipients(
        User $operator,
        ChatSystemBroadcastAudience $audience,
        array $targetIds = [],
    ): Collection {
        $query = User::query();

        return match ($audience) {
            ChatSystemBroadcastAudience::All => $query->orderBy('id')->get(['id', 'name', 'username', 'department_id']),
            ChatSystemBroadcastAudience::Departments => $query
                ->whereIn('department_id', $targetIds)
                ->orderBy('id')
                ->get(['id', 'name', 'username', 'department_id']),
            ChatSystemBroadcastAudience::Users => $query
                ->whereIn('id', $targetIds)
                ->orderBy('id')
                ->get(['id', 'name', 'username', 'department_id']),
        };
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: ?string, users_count: int}>
     */
    public function listBroadcastDepartments(): Collection
    {
        return Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'users_count' => (int) $department->users_count,
            ])
            ->values();
    }

    /**
     * @return array{path: string, original_name: string, mime: string, size: int, type: MessageType}|null
     */
    protected function storeBroadcastAttachment(?UploadedFile $attachment): ?array
    {
        if (! $attachment) {
            return null;
        }

        $mime = (string) $attachment->getMimeType();
        $type = str_starts_with($mime, 'image/') ? MessageType::Image : MessageType::File;
        $extension = $attachment->getClientOriginalExtension() ?: $attachment->extension() ?: 'bin';
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $attachment->storeAs('chat/broadcast', $filename, 'public');

        return [
            'path' => $path,
            'original_name' => $attachment->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $attachment->getSize(),
            'type' => $type,
        ];
    }

    /**
     * @param  array{path: string, original_name: string, mime: string, size: int, type: MessageType}|null  $storedAttachment
     */
    protected function sendStoredSupportMessage(
        Conversation $conversation,
        User $sender,
        MessagePersona $persona,
        ?string $body = null,
        ?array $storedAttachment = null,
    ): Message {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'persona' => $persona,
            'body' => $body,
            'type' => $storedAttachment['type'] ?? MessageType::Text,
            'attachment_path' => $storedAttachment['path'] ?? null,
            'attachment_original_name' => $storedAttachment['original_name'] ?? null,
            'attachment_mime' => $storedAttachment['mime'] ?? null,
            'attachment_size' => $storedAttachment['size'] ?? null,
        ]);

        $conversation->touch();
        $message->load('user:id,name,username');
        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        ?string $body = null,
        ?UploadedFile $attachment = null,
        MessagePersona $persona = MessagePersona::User,
        bool $skipParticipantCheck = false,
    ): Message {
        if (! $skipParticipantCheck) {
            $this->ensureCanAccess($conversation, $sender);
            if (! $conversation->isSupport()) {
                $this->ensureParticipant($conversation, $sender);
            } elseif ($persona === MessagePersona::User) {
                abort_unless((int) $conversation->support_user_id === (int) $sender->id, 403);
            } else {
                abort_unless($sender->can('chat-support-operate'), 403);
            }
        }

        $trimmedBody = filled($body) ? trim($body) : null;
        $type = MessageType::Text;
        $attachmentPath = null;
        $attachmentOriginalName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($attachment) {
            $mime = (string) $attachment->getMimeType();
            $type = str_starts_with($mime, 'image/') ? MessageType::Image : MessageType::File;
            $extension = $attachment->getClientOriginalExtension() ?: $attachment->extension() ?: 'bin';
            $filename = Str::uuid()->toString().'.'.$extension;
            $attachmentPath = $attachment->storeAs('chat/'.$conversation->id, $filename, 'public');
            $attachmentOriginalName = $attachment->getClientOriginalName();
            $attachmentMime = $mime;
            $attachmentSize = $attachment->getSize();
        }

        if ($trimmedBody === null && $attachmentPath === null) {
            throw ValidationException::withMessages([
                'body' => 'Please enter a message or attach a file.',
            ]);
        }

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'persona' => $persona,
            'body' => $trimmedBody,
            'type' => $type,
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => $attachmentOriginalName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);

        $conversation->touch();

        $message->load('user:id,name,username');

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }

    public function markDelivered(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = $this->participantOrFail($conversation, $user);
        $now = now();

        if (! $participant->last_delivered_at || $participant->last_delivered_at->lt($now)) {
            $participant->forceFill(['last_delivered_at' => $now])->save();
            broadcast(new MessageDelivered($conversation, $user, $now))->toOthers();
        }

        return $participant->fresh();
    }

    public function markRead(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = $this->participantOrFail($conversation, $user);
        $now = now();

        $participant->forceFill([
            'last_read_at' => $now,
            'last_delivered_at' => $participant->last_delivered_at && $participant->last_delivered_at->gt($now)
                ? $participant->last_delivered_at
                : $now,
        ])->save();

        broadcast(new ConversationRead($conversation, $user, $now))->toOthers();

        return $participant->fresh();
    }

    public function unreadCount(User $user): int
    {
        $participantRows = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->get(['conversation_id', 'last_read_at']);

        $total = 0;

        foreach ($participantRows as $row) {
            $query = Message::query()
                ->where('conversation_id', $row->conversation_id)
                ->where('user_id', '!=', $user->id);

            if ($row->last_read_at) {
                $query->where('created_at', '>', $row->last_read_at);
            }

            $total += $query->count();
        }

        if ($user->can('chat-support-operate')) {
            $supportThreads = Conversation::query()
                ->where('type', ConversationType::Support)
                ->where('support_user_id', '!=', $user->id)
                ->get(['id', 'support_last_read_at']);

            foreach ($supportThreads as $thread) {
                $query = Message::query()
                    ->where('conversation_id', $thread->id)
                    ->where('persona', MessagePersona::User);

                if ($thread->support_last_read_at) {
                    $query->where('created_at', '>', $thread->support_last_read_at);
                }

                $total += $query->count();
            }
        }

        return $total;
    }

    /**
     * Recent unread messages from other participants (newest first).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentUnreadMessages(User $user, int $limit = 20): Collection
    {
        $participantRows = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->get(['conversation_id', 'last_read_at']);

        if ($participantRows->isEmpty()) {
            return collect();
        }

        $messages = Message::query()
            ->with([
                'user:id,name,username',
                'conversation.participants',
            ])
            ->where('user_id', '!=', $user->id)
            ->where(function ($query) use ($participantRows): void {
                foreach ($participantRows as $index => $row) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(function ($inner) use ($row): void {
                        $inner->where('conversation_id', $row->conversation_id);
                        if ($row->last_read_at) {
                            $inner->where('created_at', '>', $row->last_read_at);
                        }
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $messages->map(function (Message $message) use ($user) {
            $conversation = $message->conversation;
            $viewerParticipant = $conversation?->participants->firstWhere('user_id', $user->id);
            $peerParticipant = $conversation?->participants->firstWhere('user_id', '!=', $user->id);

            return $message->toChatPayload($viewerParticipant, $peerParticipant);
        })->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function searchUsers(User $authUser, string $query, ?int $limit = null, bool $includeExistingPeers = false, bool $includeSelf = false): Collection
    {
        $term = trim($query);
        $resolvedLimit = $limit ?? ($term === '' ? 50 : 30);

        $peerIdsWithMessages = [];
        if (! $includeExistingPeers) {
            $peerIdsWithMessages = Conversation::query()
                ->whereHas('participants', fn ($query) => $query->where('user_id', $authUser->id))
                ->whereHas('messages')
                ->with('participants')
                ->get()
                ->flatMap(fn (Conversation $conversation) => $conversation->participants->pluck('user_id'))
                ->reject(fn ($id): bool => (int) $id === (int) $authUser->id)
                ->unique()
                ->values()
                ->all();
        }

        return User::query()
            ->when(! $includeSelf, fn ($builder) => $builder->whereKeyNot($authUser->id))
            ->when($peerIdsWithMessages !== [], fn ($builder) => $builder->whereKeyNot($peerIdsWithMessages))
            ->when($term !== '', function ($builder) use ($term): void {
                $builder->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('username', 'like', '%'.$term.'%');
                });
            })
            ->with('department:id,name')
            ->orderBy('name')
            ->limit($resolvedLimit)
            ->get(['id', 'name', 'username', 'email', 'role', 'department_id', 'last_seen_at']);
    }

    /**
     * @return array<string, mixed>
     */
    public function conversationPayload(Conversation $conversation, User $viewer, bool $forOperatorInbox = false): array
    {
        if ($conversation->isSupport()) {
            return $this->supportConversationPayload($conversation, $viewer, $forOperatorInbox);
        }

        $peer = $conversation->otherParticipant($viewer);
        $viewerParticipant = $conversation->relationLoaded('participants')
            ? $conversation->participants->firstWhere('user_id', $viewer->id)
            : $conversation->participants()->where('user_id', $viewer->id)->first();
        $peerParticipant = $conversation->relationLoaded('participants')
            ? $conversation->participants->firstWhere('user_id', $peer?->id)
            : ($peer ? $conversation->participants()->where('user_id', $peer->id)->first() : null);

        $latest = $conversation->latestMessage;
        $unreadQuery = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $viewer->id);

        if ($viewerParticipant?->last_read_at) {
            $unreadQuery->where('created_at', '>', $viewerParticipant->last_read_at);
        }

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'peer' => $peer ? $this->peerPayload($peer) : null,
            'latest_message' => $latest
                ? $latest->toChatPayload($viewerParticipant, $peerParticipant)
                : null,
            'unread_count' => $unreadQuery->count(),
            'viewer_last_read_at' => $viewerParticipant?->last_read_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supportConversationPayload(Conversation $conversation, User $viewer, bool $forOperatorInbox = false): array
    {
        if (! $conversation->relationLoaded('participants')) {
            $conversation->load('participants');
        }

        $asOperator = $forOperatorInbox
            || (
                $viewer->can('chat-support-operate')
                && (int) $conversation->support_user_id !== (int) $viewer->id
            );

        $supportUser = $conversation->relationLoaded('supportUser')
            ? $conversation->supportUser
            : $conversation->supportUser()->with('department:id,name')->first();

        $endUserParticipant = $conversation->participants->firstWhere('user_id', $conversation->support_user_id);
        [$viewerParticipant, $peerParticipant] = $this->supportReadParticipants($conversation, $asOperator, $endUserParticipant);

        $latest = $conversation->latestMessage;
        $unreadQuery = Message::query()->where('conversation_id', $conversation->id);

        if ($asOperator) {
            $unreadQuery->where('persona', MessagePersona::User);
            if ($conversation->support_last_read_at) {
                $unreadQuery->where('created_at', '>', $conversation->support_last_read_at);
            }
        } else {
            $unreadQuery->where('persona', MessagePersona::System);
            if ($endUserParticipant?->last_read_at) {
                $unreadQuery->where('created_at', '>', $endUserParticipant->last_read_at);
            }
        }

        $assignee = $conversation->relationLoaded('assignee')
            ? $conversation->assignee
            : $conversation->assignee()->first();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type->value,
            'peer' => $asOperator && $supportUser
                ? $this->peerPayload($supportUser)
                : $this->systemPeerPayload(),
            'latest_message' => $latest
                ? $latest->toChatPayload($viewerParticipant, $peerParticipant)
                : null,
            'unread_count' => $unreadQuery->count(),
            'viewer_last_read_at' => $asOperator
                ? $conversation->support_last_read_at?->toIso8601String()
                : $endUserParticipant?->last_read_at?->toIso8601String(),
            'support_status' => $conversation->support_status?->value,
            'assigned_to' => $conversation->assigned_to,
            'assignee' => $assignee ? [
                'id' => $assignee->id,
                'name' => $assignee->name,
                'username' => $assignee->username,
            ] : null,
            'support_user_id' => $conversation->support_user_id,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{0: ?ConversationParticipant, 1: ?ConversationParticipant}
     */
    public function supportReadParticipants(Conversation $conversation, bool $asOperator, ?ConversationParticipant $endUserParticipant = null): array
    {
        $endUserParticipant ??= $conversation->relationLoaded('participants')
            ? $conversation->participants->firstWhere('user_id', $conversation->support_user_id)
            : $conversation->participants()->where('user_id', $conversation->support_user_id)->first();

        if ($asOperator) {
            $viewerParticipant = new ConversationParticipant([
                'last_read_at' => $conversation->support_last_read_at,
                'last_delivered_at' => $conversation->support_last_read_at,
            ]);

            return [$viewerParticipant, $endUserParticipant];
        }

        // End-user view: SPFI-MS receives the message as soon as it is stored.
        // Delivered must always cover the latest message (use now()), while read
        // still follows support_last_read_at only.
        $peerParticipant = new ConversationParticipant([
            'last_read_at' => $conversation->support_last_read_at,
            'last_delivered_at' => now(),
        ]);

        return [$endUserParticipant, $peerParticipant];
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(Message $message, User $viewer, bool $asOperator = false): array
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->with('participants')->firstOrFail();

        if (! $conversation->relationLoaded('participants')) {
            $conversation->load('participants');
        }

        if ($conversation->isSupport()) {
            $resolvedAsOperator = $asOperator
                || (
                    $viewer->can('chat-support-operate')
                    && (int) $conversation->support_user_id !== (int) $viewer->id
                );
            [$viewerParticipant, $peerParticipant] = $this->supportReadParticipants($conversation, $resolvedAsOperator);

            return $message->toChatPayload($viewerParticipant, $peerParticipant);
        }

        $viewerParticipant = $conversation->participants->firstWhere('user_id', $viewer->id);
        $peerParticipant = $conversation->participants->firstWhere('user_id', '!=', $viewer->id);

        return $message->toChatPayload($viewerParticipant, $peerParticipant);
    }

    /**
     * @return array{id: ?int, name: string, username: string, email: ?string, role: ?string, department: ?string, is_online: bool, last_seen_at: ?string, is_official: bool, avatar_url: string}
     */
    public function systemPeerPayload(): array
    {
        return [
            'id' => null,
            'name' => self::SYSTEM_DISPLAY_NAME,
            'username' => self::SYSTEM_USERNAME,
            'email' => null,
            'role' => null,
            'department' => null,
            'is_online' => true,
            'last_seen_at' => null,
            'is_official' => true,
            'avatar_url' => self::SYSTEM_AVATAR_PATH,
        ];
    }

    public function ensureParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->hasParticipant($user->id), 403);
    }

    public function participantOrFail(Conversation $conversation, User $user): ConversationParticipant
    {
        $participant = $conversation->relationLoaded('participants')
            ? $conversation->participants->firstWhere('user_id', $user->id)
            : $conversation->participants()->where('user_id', $user->id)->first();

        abort_unless($participant !== null, 403);

        return $participant;
    }

    /**
     * @return array{id: int, name: string, username: string, email: ?string, role: ?string, department: ?string, is_online: bool, last_seen_at: ?string, is_official: bool, avatar_url: ?string}
     */
    public function peerPayload(User $user): array
    {
        if (! $user->relationLoaded('department')) {
            $user->load('department:id,name');
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department?->name,
            'is_online' => $user->isOnline(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'is_official' => false,
            'avatar_url' => null,
        ];
    }

    /**
     * @return array{id: int, name: string, username: string, email: ?string, role: ?string, department: ?string, is_online: bool, last_seen_at: ?string, online_threshold_seconds: int}
     */
    public function userPresencePayload(User $user): array
    {
        return [
            ...$this->peerPayload($user),
            'online_threshold_seconds' => Session::ONLINE_THRESHOLD_SECONDS,
        ];
    }
}
