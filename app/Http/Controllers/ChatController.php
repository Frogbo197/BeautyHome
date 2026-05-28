<?php

namespace App\Http\Controllers;

use App\Service\Chat\ChatMessageService;
use App\Service\Chat\ChatHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatMessageService $chat,
        private readonly ChatHistoryService $history,
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        // TODO: Khi co auth/session, thay user_id request bang principal server-side.
        return $this->chat->send($request);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // TODO: Khi co auth/session, thay user_id request bang principal server-side.
            'user_id' => 'required|integer',
            'session_id' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'success' => true,
            'history' => $this->history->history(
                (int) $validated['user_id'],
                $validated['session_id'] ?? 'default'
            ),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // TODO: Khi co auth/session, thay user_id request bang principal server-side.
            'user_id' => 'required|integer',
            'session_id' => 'nullable|string|max:64',
        ]);

        $this->history->clear(
            (int) $validated['user_id'],
            $validated['session_id'] ?? 'default'
        );

        return response()->json(['success' => true]);
    }
}
