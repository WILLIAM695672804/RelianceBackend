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
            // Deux pièges ici : la relation n'est pas toujours chargée (dernier message d'une
            // conversation), et la colonne JSON `attachments` masque la relation homonyme —
            // `$this->attachments` renvoie donc la colonne (nulle). D'où `getRelation`.
            'attachments'     => $this->relationLoaded('attachments')
                ? MessageAttachmentResource::collection($this->getRelation('attachments'))
                : [],
            'delivered'       => $this->delivered,
            'read_at'         => $this->read_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
