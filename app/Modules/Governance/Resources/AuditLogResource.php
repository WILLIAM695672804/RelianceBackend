<?php

namespace App\Modules\Governance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'actor_id'    => $this->actor_id,
            'actor_name'  => $this->whenLoaded('actor', fn () => $this->actor->name),
            'object_type' => $this->object_type,
            'object_id'   => $this->object_id,
            'action'      => $this->action,
            'details'     => $this->details,
            'created_at'  => $this->created_at,
        ];
    }
}
