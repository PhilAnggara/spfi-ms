<?php

namespace App\Http\Controllers;

use App\Enums\ChatSystemBroadcastAudience;
use App\Enums\MessagePersona;
use App\Enums\SupportConversationStatus;
use App\Http\Requests\SearchChatUsersRequest;
use App\Http\Requests\StoreChatConversationRequest;
use App\Http\Requests\StoreChatDirectMessageRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\StoreChatSupportConversationRequest;
use App\Http\Requests\StoreChatSystemBroadcastRequest;
use App\Http\Requests\StoreChatTypingRequest;
use App\Http\Requests\UpdateSupportConversationStatusRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $this->chatService->listConversations($user);

        return response()->json([
            'data' => $items,
            'unread_count' => $this->chatService->unreadCount($user),
        ]);
    }

    public function supportConversations(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('chat-support-operate'), 403);

        $user = $request->user();
        $items = $this->chatService->listSupportConversations($user);

        return response()->json([
            'data' => $items,
            'unread_count' => $this->chatService->unreadCount($user),
        ]);
    }

    public function storeSupportConversation(StoreChatSupportConversationRequest $request): JsonResponse
    {
        $endUser = User::query()->findOrFail((int) $request->validated('user_id'));
        $conversation = $this->chatService->findOrCreateSupportThread($endUser);

        return response()->json([
            'data' => $this->chatService->conversationPayload($conversation, $request->user(), forOperatorInbox: true),
        ], 201);
    }

    public function supportDepartments(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('chat-support-operate'), 403);

        return response()->json([
            'data' => $this->chatService->listBroadcastDepartments(),
        ]);
    }

    public function broadcastSystemMessage(StoreChatSystemBroadcastRequest $request): JsonResponse
    {
        $result = $this->chatService->broadcastSystemMessage(
            $request->user(),
            ChatSystemBroadcastAudience::from((string) $request->validated('audience')),
            $request->validated('target_ids', []),
            $request->validated('body'),
            $request->file('attachment'),
        );

        return response()->json([
            'data' => $result,
        ], 201);
    }

    public function storeConversation(StoreChatConversationRequest $request): JsonResponse
    {
        $peer = User::query()->findOrFail((int) $request->validated('user_id'));
        $conversation = $this->chatService->findOrCreateDirect($request->user(), $peer);

        return response()->json([
            'data' => $this->chatService->conversationPayload($conversation, $request->user()),
        ], 201);
    }

    public function storeDirectMessage(StoreChatDirectMessageRequest $request): JsonResponse
    {
        $peer = User::query()->findOrFail((int) $request->validated('user_id'));
        $result = $this->chatService->sendDirectMessage(
            $request->user(),
            $peer,
            $request->validated('body'),
            $request->file('attachment'),
        );

        return response()->json([
            'data' => [
                'conversation' => $this->chatService->conversationPayload($result['conversation'], $request->user()),
                'message' => $this->chatService->messagePayload($result['message'], $request->user()),
            ],
        ], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->chatService->ensureCanAccess($conversation, $request->user());

        $conversation->load(['participants', 'supportUser.department:id,name', 'assignee:id,name,username']);
        $paginator = $this->chatService->paginateMessages($conversation);
        $viewer = $request->user();
        $asOperator = $conversation->isSupport()
            && $viewer->can('chat-support-operate')
            && $request->boolean('as_operator');

        if ($conversation->isSupport()) {
            [$viewerParticipant, $peerParticipant] = $this->chatService->supportReadParticipants($conversation, $asOperator);
        } else {
            $viewerParticipant = $conversation->participants->firstWhere('user_id', $viewer->id);
            $peerParticipant = $conversation->participants->firstWhere('user_id', '!=', $viewer->id);
        }

        $data = collect($paginator->items())->map(
            fn ($message) => $message->toChatPayload($viewerParticipant, $peerParticipant)
        )->values();

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'conversation' => $this->chatService->conversationPayload(
                $conversation,
                $viewer,
                forOperatorInbox: $asOperator,
            ),
        ]);
    }

    public function storeMessage(StoreChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $persona = MessagePersona::User;

        if ($conversation->isSupport()) {
            $wantsSystem = $request->boolean('as_system') && $user->can('chat-support-operate');
            $isEndUser = (int) $conversation->support_user_id === (int) $user->id;

            if ($wantsSystem) {
                $persona = MessagePersona::System;
            } elseif ($isEndUser) {
                $persona = MessagePersona::User;
            } elseif ($user->can('chat-support-operate')) {
                $persona = MessagePersona::System;
            } else {
                abort(403);
            }

            $result = $this->chatService->sendSupportMessage(
                $conversation,
                $user,
                $request->validated('body'),
                $request->file('attachment'),
                $persona,
            );

            return response()->json([
                'data' => $this->chatService->messagePayload(
                    $result['message'],
                    $user,
                    asOperator: $persona === MessagePersona::System,
                ),
            ], 201);
        }

        $message = $this->chatService->sendMessage(
            $conversation,
            $user,
            $request->validated('body'),
            $request->file('attachment'),
        );

        return response()->json([
            'data' => $this->chatService->messagePayload($message, $user),
        ], 201);
    }

    public function markDelivered(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->isSupport()
            && $request->user()->can('chat-support-operate')
            && ($request->boolean('as_operator') || ! $conversation->hasParticipant($request->user()->id))) {
            return response()->json([
                'data' => [
                    'conversation_id' => $conversation->id,
                    'last_delivered_at' => $conversation->support_last_read_at?->toIso8601String(),
                ],
            ]);
        }

        $participant = $this->chatService->markDelivered($conversation, $request->user());

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'last_delivered_at' => $participant->last_delivered_at?->toIso8601String(),
            ],
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->isSupport()
            && $request->user()->can('chat-support-operate')
            && ($request->boolean('as_operator') || ! $conversation->hasParticipant($request->user()->id))) {
            $conversation = $this->chatService->markSupportRead($conversation, $request->user());

            return response()->json([
                'data' => [
                    'conversation_id' => $conversation->id,
                    'last_read_at' => $conversation->support_last_read_at?->toIso8601String(),
                ],
            ]);
        }

        $participant = $this->chatService->markRead($conversation, $request->user());

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'last_read_at' => $participant->last_read_at?->toIso8601String(),
            ],
        ]);
    }

    public function assignSupport(Request $request, Conversation $conversation): JsonResponse
    {
        $conversation = $this->chatService->assignSupportConversation($conversation, $request->user());

        return response()->json([
            'data' => $this->chatService->conversationPayload($conversation, $request->user(), forOperatorInbox: true),
        ]);
    }

    public function updateSupportStatus(UpdateSupportConversationStatusRequest $request, Conversation $conversation): JsonResponse
    {
        $status = SupportConversationStatus::from((string) $request->validated('status'));
        $conversation = $this->chatService->updateSupportStatus($conversation, $request->user(), $status);

        return response()->json([
            'data' => $this->chatService->conversationPayload($conversation, $request->user(), forOperatorInbox: true),
        ]);
    }

    public function searchUsers(SearchChatUsersRequest $request): JsonResponse
    {
        $forBroadcast = $request->boolean('for_broadcast') && $request->user()->can('chat-support-operate');
        $forSupportSearch = $request->boolean('for_support_search') && $request->user()->can('chat-support-operate');
        $includeExistingPeers = $forBroadcast || $forSupportSearch;

        $users = $this->chatService->searchUsers(
            $request->user(),
            (string) $request->validated('q', ''),
            ($forBroadcast || $forSupportSearch) ? 100 : null,
            includeExistingPeers: $includeExistingPeers,
            includeSelf: $forBroadcast,
        );

        return response()->json([
            'data' => $users->map(fn ($user) => $this->chatService->userPresencePayload($user))->values(),
        ]);
    }

    public function typing(StoreChatTypingRequest $request): JsonResponse
    {
        $peer = User::query()->findOrFail((int) $request->validated('user_id'));

        $this->chatService->broadcastTyping(
            $request->user(),
            $peer,
            (bool) $request->validated('typing'),
            $request->validated('conversation_id'),
        );

        return response()->json(['ok' => true]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->chatService->unreadCount($request->user()),
        ]);
    }

    public function unreadMessages(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->chatService->recentUnreadMessages($request->user()),
        ]);
    }
}
