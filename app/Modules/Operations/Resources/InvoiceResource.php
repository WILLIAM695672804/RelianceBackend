<?php

namespace App\Modules\Operations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'quote_id'         => $this->quote_id,
            'client_id'        => $this->client_id,
            'issuer_id'        => $this->issuer_id,
            'client'           => $this->whenLoaded('client', fn () => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ]),
            'issuer'           => $this->whenLoaded('issuer', fn () => [
                'id'   => $this->issuer->id,
                'name' => $this->issuer->name,
            ]),
            'amount'           => $this->amount,
            'tax_amount'       => $this->tax_amount,
            // Frais d'intervention (commission de plateforme) : affichés avant paiement.
            'platform_fee'     => app(\App\Modules\Finance\Services\PlatformFeeService::class)->forAmount((float) $this->amount, 'job'),
            'net_to_issuer'    => app(\App\Modules\Finance\Services\PlatformFeeService::class)->breakdown((float) $this->amount, 'job')['net'],
            'reference'        => $this->reference,
            'external_pdf_ref' => $this->external_pdf_ref,
            'status'           => $this->status,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
