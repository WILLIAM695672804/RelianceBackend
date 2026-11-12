<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Conversation;
use App\Modules\Social\Models\Message;
use Illuminate\Http\Request;

class AdminConversationController extends Controller
{
    /**
     * GET /v1/admin/conversations — All conversations (read-only supervision).
     */
    public function index(Request $request)
    {
        $conversations = Conversation::with(['members.user:id,name', 'lastMessage'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * GET /v1/admin/conversations/{id} — Conversation detail with messages.
     */
    public function show(string $id)
    {
        $conversation = Conversation::with(['members.user:id,name', 'messages.sender'])
            ->findOrFail($id);

        return response()->json(['data' => $conversation]);
    }

    /**
     * GET /v1/admin/conversations/search — Search within messages.
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $messages = Message::where('body', 'ilike', "%{$request->q}%")
            ->with(['sender', 'conversation'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($messages);
    }
}
