<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Conversation;
use App\Modules\Social\Requests\StoreConversationRequest;
use App\Modules\Social\Resources\ConversationResource;
use App\Modules\Social\Services\ConversationService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    /**
     * Lister mes conversations (triees par dernier message).
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['members.user:id,name,avatar_url', 'lastMessage.sender:id,name'])
        ->orderByDesc(function ($q) {
            $q->select('created_at')
                ->from('messages')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->latest()
                ->limit(1);
        })
        ->paginate($request->integer('per_page', 20));

        return ConversationResource::collection($conversations);
    }

    /**
     * Creer une conversation (ou retrouver une existante en prive).
     */
    public function store(StoreConversationRequest $request)
    {
        $validated = $request->validated();

        $conversation = $this->conversationService->createOrFind(
            $request->user()->id,
            $validated['members'],
            $validated['subject'] ?? null,
            $validated['initial_message'] ?? null,
        );

        $conversation->load(['members.user:id,name,avatar_url', 'lastMessage.sender:id,name']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Conversation creee',
            'data'    => new ConversationResource($conversation),
        ], 201);
    }

    /**
     * Voir une conversation (avec verification d'appartenance).
     */
    public function show(Request $request, string $id)
    {
        $conversation = Conversation::with(['members.user:id,name,avatar_url', 'lastMessage.sender:id,name'])
            ->findOrFail($id);

        if (!$conversation->hasMember($request->user()->id)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        return new ConversationResource($conversation);
    }

    /**
     * Nombre total de messages non lus.
     */
    public function unreadCount(Request $request)
    {
        $count = $this->conversationService->totalUnreadCount($request->user()->id);

        return response()->json(['unread_count' => $count]);
    }
}
