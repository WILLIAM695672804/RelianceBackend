<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $membership = $this->whenLoaded('members', function () use ($user) {
            return $this->members->firstWhere('user_id', $user?->id);
        });

        return [
            'id'           => $this->id,
            'subject'      => $this->subject,
            'members'      => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($m) => [
                    'user_id'      => $m->user_id,
                    'name'         => $m->user->name ?? null,
                    'avatar_url'   => $m->user->avatar_url ?? null,
                    'joined_at'    => $m->joined_at?->toISOString(),
                    'unread_count' => $m->unread_count,
                ])
            ),
            'last_message' => $this->whenLoaded('lastMessage', fn () =>
                $this->lastMessage ? new MessageResource($this->lastMessage) : null
            ),
            'unread_count' => $membership?->unread_count ?? 0,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
