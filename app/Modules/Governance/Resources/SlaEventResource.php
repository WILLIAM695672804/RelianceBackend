<?php

namespace App\Modules\Governance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SlaEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'request_id'  => $this->request_id,
            'event_key'   => $this->event_key,
            'occurred_at' => $this->occurred_at,
            'payload'     => $this->payload,
            'request'     => $this->whenLoaded('request'),
        ];
    }
}
