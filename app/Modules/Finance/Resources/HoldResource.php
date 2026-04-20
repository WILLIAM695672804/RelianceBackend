<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HoldResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'released_at' => $this->released_at,
            'related_payment' => $this->related_payment,
            'created_at' => $this->created_at,
        ];
    }
}
