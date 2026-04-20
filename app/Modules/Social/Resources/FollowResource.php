<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'follower_id' => $this->follower_id,
            'followee_id' => $this->followee_id,
            'follower'    => $this->whenLoaded('follower', fn () => [
                'id'         => $this->follower->id,
                'name'       => $this->follower->name,
                'avatar_url' => $this->follower->avatar_url,
            ]),
            'followee'    => $this->whenLoaded('followee', fn () => [
                'id'         => $this->followee->id,
                'name'       => $this->followee->name,
                'avatar_url' => $this->followee->avatar_url,
            ]),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
