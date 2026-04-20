<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'type' => $this->type,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
