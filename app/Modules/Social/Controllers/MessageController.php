<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Conversation;
use App\Modules\Social\Models\Message;
use App\Modules\Social\Models\MessageAttachment;
use App\Modules\Social\Requests\StoreMessageRequest;
use App\Modules\Social\Resources\MessageAttachmentResource;
use App\Modules\Social\Resources\MessageResource;
use App\Modules\Social\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    public function index(Request $request, string $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->hasMember($request->user()->id)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with(['sender:id,name,avatar_url', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 30));

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, string $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $userId = $request->user()->id;

        if (!$conversation->hasMember($userId)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $validated = $request->validated();
        $storedAttachments = null;

        if ($request->hasFile('attachments')) {
            $storedAttachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("conversations/{$conversationId}", 'public');
                $storedAttachments[] = [
                    'path' => $path,
                    'type' => $file->getMimeType(),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $message = $this->conversationService->sendMessage(
            $conversationId,
            $userId,
            $validated['body'] ?? '',
            $storedAttachments,
        );

        $message->load('attachments');

        return response()->json([
            'status'  => 'success',
            'message' => 'Message envoye',
            'data'    => new MessageResource($message),
        ], 201);
    }

    public function markAsRead(Request $request, string $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $userId = $request->user()->id;

        if (!$conversation->hasMember($userId)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $this->conversationService->markAsRead($conversationId, $userId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Messages marques comme lus',
        ]);
    }

    public function attachments(Request $request, string $conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->hasMember($request->user()->id)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $query = MessageAttachment::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc');

        $type = $request->query('type');
        if ($type === 'image') {
            $query->where('file_type', 'like', 'image/%');
        } elseif ($type === 'document') {
            $query->whereIn('file_type', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
        } elseif ($type === 'video') {
            $query->where('file_type', 'like', 'video/%');
        }

        $attachments = $query->paginate($request->integer('per_page', 20));

        return MessageAttachmentResource::collection($attachments);
    }

    public function destroy(Request $request, string $conversationId, string $messageId)
    {
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->hasMember($request->user()->id)) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $message = Message::where('conversation_id', $conversationId)->findOrFail($messageId);

        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        foreach ($message->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $message->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Message supprime',
        ]);
    }
}
