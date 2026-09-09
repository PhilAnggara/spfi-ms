<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScreenMessageReplyRequest;
use App\Models\ScreenMessage;
use App\Services\ScreenMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreenMessageInboxController extends Controller
{
    public function __construct(private ScreenMessageService $screenMessages) {}

    public function pending(Request $request): JsonResponse
    {
        $messages = $this->screenMessages
            ->pendingFor($request->user())
            ->map(fn (ScreenMessage $message) => $message->toOverlayPayload())
            ->values();

        return response()->json(['messages' => $messages]);
    }

    public function markSeen(Request $request, ScreenMessage $screenMessage): JsonResponse
    {
        $this->screenMessages->markSeen($screenMessage, $request->user());

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, ScreenMessage $screenMessage): JsonResponse
    {
        $this->screenMessages->dismiss($screenMessage, $request->user());

        return response()->json(['ok' => true]);
    }

    public function reply(StoreScreenMessageReplyRequest $request, ScreenMessage $screenMessage): JsonResponse
    {
        $reply = $this->screenMessages->reply(
            $screenMessage,
            $request->user(),
            (string) $request->validated('body'),
        );

        return response()->json([
            'ok' => true,
            'reply' => [
                'id' => $reply->id,
                'body' => $reply->body,
            ],
        ]);
    }
}
