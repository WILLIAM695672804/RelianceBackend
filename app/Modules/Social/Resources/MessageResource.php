<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id'       => $this->sender_id,
            'sender'          => $this->whenLoaded('sender', fn () => [
                'id'         => $this->sender->id,
                'name'       => $this->sender->name,
                'avatar_url' => $this->sender->avatar_url,
            ]),
            'body'            => $this->body,
            'attachments'     => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'delivered'       => $this->delivered,
            'read_at'         => $this->read_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
