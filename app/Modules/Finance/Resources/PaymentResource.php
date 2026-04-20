<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'payer_id' => $this->payer_id,
            'payee_id' => $this->payee_id,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'external_ref' => $this->external_ref,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'settled_at' => $this->settled_at,
        ];
    }
}
