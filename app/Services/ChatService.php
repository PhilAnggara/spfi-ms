<?php

namespace App\Services;

use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Events\ChatTyping;
use App\Events\ConversationRead;
use App\Events\MessageDelivered;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Session;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listConversations(User $user): Collection
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('messages')
            ->with([
                'latestMessage.user',
                'participants',
                'users' => fn ($query) => $query->with([
                    'department:id,name',
                    'sessions' => fn ($sessions) => $sessions->select('id', 'user_id', 'last_activity'),
                ]),
            ])
            ->get()
            ->sortByDesc(fn (Conversation $conversation) => $conversation->latestMessage?->created_at?->timestamp ?? $conversation->updated_at?->timestamp ?? 0)
            ->values();

        return $conversations->map(fn (Conversation $conversation): array => $this->conversationPayload($conversation, $user));
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

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        ?string $body = null,
        ?UploadedFile $attachment = null,
    ): Message {
        $this->ensureParticipant($conversation, $sender);

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

        if ($participantRows->isEmpty()) {
            return 0;
        }

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
    public function searchUsers(User $authUser, string $query, ?int $limit = null): Collection
    {
        $term = trim($query);
        $resolvedLimit = $limit ?? ($term === '' ? 50 : 30);

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

        return User::query()
            ->whereKeyNot($authUser->id)
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
    public function conversationPayload(Conversation $conversation, User $viewer): array
    {
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
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(Message $message, User $viewer): array
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->with('participants')->firstOrFail();

        if (! $conversation->relationLoaded('participants')) {
            $conversation->load('participants');
        }

        $viewerParticipant = $conversation->participants->firstWhere('user_id', $viewer->id);
        $peerParticipant = $conversation->participants->firstWhere('user_id', '!=', $viewer->id);

        return $message->toChatPayload($viewerParticipant, $peerParticipant);
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
     * @return array{id: int, name: string, username: string, email: ?string, role: ?string, department: ?string, is_online: bool, last_seen_at: ?string}
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
