<?php

namespace App\Modules\Governance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->user_id,
            'event_name'  => $this->event_name,
            'event_props' => $this->event_props,
            'created_at'  => $this->created_at,
        ];
    }
}
