<?php

namespace App\Modules\Finance\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MomoGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiUser;
    private string $subscriptionKey;

    public function __construct()
    {
        $this->baseUrl = config('services.momo.base_url');
        $this->apiKey = config('services.momo.api_key');
        $this->apiUser = config('services.momo.api_user');
        $this->subscriptionKey = config('services.momo.subscription_key');
    }

    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array
    {
        $referenceId = (string) Str::uuid();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
            'X-Reference-Id' => $referenceId,
            'X-Target-Environment' => config('services.momo.environment', 'sandbox'),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->post("{$this->baseUrl}/collection/v1_0/requesttopay", [
            'amount' => (string) $amount,
            'currency' => $currency,
            'externalId' => $metadata['payment_id'] ?? $referenceId,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $phoneNumber,
            ],
            'payerMessage' => $metadata['message'] ?? 'Paiement Reliance',
            'payeeNote' => $metadata['note'] ?? 'Recharge wallet',
        ]);

        return [
            'success' => $response->successful(),
            'external_ref' => $referenceId,
            'status' => $response->successful() ? 'pending' : 'failed',
            'raw' => $response->json(),
        ];
    }

    public function checkStatus(string $externalRef): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
            'X-Target-Environment' => config('services.momo.environment', 'sandbox'),
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->get("{$this->baseUrl}/collection/v1_0/requesttopay/{$externalRef}");

        $data = $response->json();
        return [
            'status' => $data['status'] ?? 'UNKNOWN',
            'financial_transaction_id' => $data['financialTransactionId'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $externalRef, float $amount): array
    {
        // MTN MOMO refund via disbursement API
        return ['status' => 'not_implemented'];
    }

    private function getToken(): string
    {
        $response = Http::withBasicAuth($this->apiUser, $this->apiKey)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->subscriptionKey])
            ->post("{$this->baseUrl}/collection/token/");

        return $response->json('access_token');
    }
}
