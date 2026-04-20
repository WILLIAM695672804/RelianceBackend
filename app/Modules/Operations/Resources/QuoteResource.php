<?php

namespace App\Modules\Operations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'request_id'   => $this->request_id,
            'issuer_id'    => $this->issuer_id,
            'issuer'       => $this->whenLoaded('issuer', fn () => [
                'id'   => $this->issuer->id,
                'name' => $this->issuer->name,
            ]),
            'total_amount' => $this->total_amount,
            'breakdown'    => $this->breakdown,
            'status'       => $this->status,
            'valid_until'  => $this->valid_until?->toDateString(),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
