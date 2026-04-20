<?php

namespace App\Modules\Finance\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OrangeMoneyGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $merchantKey;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.orange_money.base_url');
        $this->merchantKey = config('services.orange_money.merchant_key');
        $this->apiKey = config('services.orange_money.api_key');
    }

    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array
    {
        $referenceId = (string) Str::uuid();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getToken(),
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/mp/pay", [
                'merchant_key' => $this->merchantKey,
                'currency' => $currency,
                'order_id' => $referenceId,
                'amount' => (string) $amount,
                'return_url' => config('app.url') . '/api/v1/webhooks/om',
                'cancel_url' => config('app.url') . '/payment/cancel',
                'notif_url' => config('app.url') . '/api/v1/webhooks/om',
                'lang' => 'fr',
                'reference' => $metadata['payment_id'] ?? $referenceId,
            ]);

            return [
                'success' => $response->successful(),
                'external_ref' => $referenceId,
                'status' => $response->successful() ? 'pending' : 'failed',
                'raw' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'external_ref' => $referenceId,
                'status' => 'failed',
                'raw' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function checkStatus(string $externalRef): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getToken(),
        ])->get("{$this->baseUrl}/mp/pay/{$externalRef}");

        $data = $response->json();
        return [
            'status' => $data['status'] ?? 'UNKNOWN',
            'financial_transaction_id' => $data['txnid'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $externalRef, float $amount): array
    {
        return ['status' => 'not_implemented'];
    }

    private function getToken(): string
    {
        $response = Http::asForm()->post("{$this->baseUrl}/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->merchantKey,
            'client_secret' => $this->apiKey,
        ]);

        return $response->json('access_token');
    }
}
