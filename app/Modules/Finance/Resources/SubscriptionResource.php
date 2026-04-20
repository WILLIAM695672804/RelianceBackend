<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subscriber_id' => $this->subscriber_id,
            'plan_name' => $this->plan_name,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'recurring' => $this->recurring,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'is_active' => $this->isActive(),
        ];
    }
}
