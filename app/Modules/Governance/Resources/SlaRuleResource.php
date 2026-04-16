<?php

namespace App\Modules\Governance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SlaRuleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'event_key'     => $this->event_key,
            'penalty_type'  => $this->penalty_type,
            'penalty_value' => $this->penalty_value,
            'active'        => $this->active,
            'meta'          => $this->meta,
            'created_at'    => $this->created_at,
        ];
    }
}
