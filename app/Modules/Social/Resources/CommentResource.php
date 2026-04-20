<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'post_id'    => $this->post_id,
            'author_id'  => $this->author_id,
            'author'     => $this->whenLoaded('author', fn () => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'avatar_url' => $this->author->avatar_url,
            ]),
            'content'    => $this->content,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
