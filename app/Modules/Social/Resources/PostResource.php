<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();

        return [
            'id'              => $this->id,
            'author_id'       => $this->author_id,
            'author'          => $this->whenLoaded('author', fn () => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'avatar_url' => $this->author->avatar_url,
                'is_verified' => $this->author->is_verified ?? false,
            ]),
            'content'         => $this->content,
            'attachments'     => $this->attachments,
            'likes_count'     => $this->whenCounted('likes', $this->likes_count ?? 0),
            'comments_count'  => $this->whenCounted('comments', $this->comments_count ?? 0),
            'is_liked'        => $user ? $this->isLikedBy($user->id) : false,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
