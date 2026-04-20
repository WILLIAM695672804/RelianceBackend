<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'balance' => (float) $this->balance,
            'available_balance' => $this->availableBalance(),
            'currency' => $this->currency,
            'held_amount' => (float) $this->activeHolds()->sum('amount'),
            'updated_at' => $this->updated_at,
        ];
    }
}
