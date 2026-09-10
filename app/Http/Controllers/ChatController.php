<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchChatUsersRequest;
use App\Http\Requests\StoreChatConversationRequest;
use App\Http\Requests\StoreChatDirectMessageRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\StoreChatTypingRequest;
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
        $this->chatService->ensureParticipant($conversation, $request->user());

        $conversation->load('participants');
        $paginator = $this->chatService->paginateMessages($conversation);
        $viewer = $request->user();
        $viewerParticipant = $conversation->participants->firstWhere('user_id', $viewer->id);
        $peerParticipant = $conversation->participants->firstWhere('user_id', '!=', $viewer->id);

        $data = collect($paginator->items())->map(
            fn ($message) => $message->toChatPayload($viewerParticipant, $peerParticipant)
        )->values();

        return response()->json([
            'data' => $data,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
        ]);
    }

    public function storeMessage(StoreChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->chatService->sendMessage(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $request->file('attachment'),
        );

        return response()->json([
            'data' => $this->chatService->messagePayload($message, $request->user()),
        ], 201);
    }

    public function markDelivered(Request $request, Conversation $conversation): JsonResponse
    {
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
        $participant = $this->chatService->markRead($conversation, $request->user());

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'last_read_at' => $participant->last_read_at?->toIso8601String(),
            ],
        ]);
    }

    public function searchUsers(SearchChatUsersRequest $request): JsonResponse
    {
        $users = $this->chatService->searchUsers(
            $request->user(),
            (string) $request->validated('q', ''),
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
}
