<?php

namespace App\Modules\Social\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Modules\Social\Models\Conversation;
use App\Modules\Social\Models\ConversationMember;
use App\Modules\Social\Models\Message;
use App\Modules\Social\Models\MessageAttachment;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function createOrFind(string $creatorId, array $memberIds, ?string $subject = null, ?string $initialMessage = null): Conversation
    {
        $allMembers = collect($memberIds)->push($creatorId)->unique()->values();

        if ($allMembers->count() === 2) {
            $existing = $this->findPrivateConversation($allMembers[0], $allMembers[1]);
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($allMembers, $subject, $initialMessage, $creatorId) {
            $conversation = Conversation::create(['subject' => $subject]);

            foreach ($allMembers as $memberId) {
                ConversationMember::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $memberId,
                    'unread_count'    => 0,
                ]);
            }

            if ($initialMessage) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id'       => $creatorId,
                    'body'            => $initialMessage,
                ]);

                ConversationMember::where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $creatorId)
                    ->increment('unread_count');

                broadcast(new MessageSent($message))->toOthers();
            }

            return $conversation->load(['members.user', 'lastMessage']);
        });
    }

    public function sendMessage(string $conversationId, string $senderId, string $body, ?array $storedAttachments = null): Message
    {
        return DB::transaction(function () use ($conversationId, $senderId, $body, $storedAttachments) {
            $message = Message::create([
                'conversation_id' => $conversationId,
                'sender_id'       => $senderId,
                'body'            => $body,
            ]);

            if ($storedAttachments) {
                foreach ($storedAttachments as $file) {
                    MessageAttachment::create([
                        'message_id'      => $message->id,
                        'conversation_id' => $conversationId,
                        'file_name'       => $file['name'],
                        'file_path'       => $file['path'],
                        'file_type'       => $file['type'],
                        'file_size'       => $file['size'],
                    ]);
                }
            }

            ConversationMember::where('conversation_id', $conversationId)
                ->where('user_id', '!=', $senderId)
                ->increment('unread_count');

            $message->load('sender');

            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });
    }

    public function markAsRead(string $conversationId, string $userId): void
    {
        DB::transaction(function () use ($conversationId, $userId) {
            $now = now();

            Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->update([
                    'read_at'   => $now,
                    'delivered' => true,
                ]);

            ConversationMember::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->update(['unread_count' => 0]);

            broadcast(new MessageRead($conversationId, $userId, $now->toISOString()))->toOthers();
        });
    }

    public function totalUnreadCount(string $userId): int
    {
        return ConversationMember::where('user_id', $userId)
            ->sum('unread_count');
    }

    private function findPrivateConversation(string $userA, string $userB): ?Conversation
    {
        // Une conversation privée = contient A, contient B, et aucun autre membre.
        // NB : on évite withCount(...)->having('members_count', ...) car PostgreSQL
        // n'autorise pas de référencer un alias de SELECT dans HAVING.
        return Conversation::whereHas('members', function ($q) use ($userA) {
            $q->where('user_id', $userA);
        })
        ->whereHas('members', function ($q) use ($userB) {
            $q->where('user_id', $userB);
        })
        ->whereDoesntHave('members', function ($q) use ($userA, $userB) {
            $q->whereNotIn('user_id', [$userA, $userB]);
        })
        ->first();
    }
}
