<?php

namespace App\Modules\Finance\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle NotchPay (agrégateur) en mode API directe.
 * Une seule intégration couvre MTN MoMo, Orange Money et carte via les "channels".
 *
 * @see https://developer.notchpay.co/accept-payments/mobile-money
 */
class NotchPayGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $publicKey;
    private ?string $privateKey;
    private string $channel;

    /** Correspondance method interne → channel NotchPay. */
    private const CHANNELS = [
        'momo' => 'cm.mtn',
        'om' => 'cm.orange',
        'card' => 'card',
    ];

    public function __construct(string $method = 'momo')
    {
        $this->baseUrl = rtrim(config('services.notchpay.base_url'), '/');
        $this->publicKey = config('services.notchpay.public_key');
        $this->privateKey = config('services.notchpay.private_key');
        $this->channel = self::CHANNELS[$method] ?? self::CHANNELS['momo'];
    }

    public function initiate(float $amount, string $currency, string $phoneNumber, array $metadata = []): array
    {
        $reference = $metadata['payment_id'] ?? (string) \Illuminate\Support\Str::uuid();

        try {
            // Étape 1 — créer le paiement (renvoie la référence NotchPay).
            $create = Http::withHeaders([
                'Authorization' => $this->publicKey,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/payments", [
                'amount' => $amount,
                'currency' => $currency,
                'phone' => $phoneNumber,
                'reference' => $reference,
                'description' => $metadata['description'] ?? 'Recharge wallet Reliance',
                'callback' => config('app.url') . '/api/v1/webhooks/notchpay',
            ]);

            if (!$create->successful()) {
                Log::warning('NotchPay création paiement échouée', ['response' => $create->json()]);
                return $this->failure($reference, $create->json());
            }

            $notchRef = $create->json('transaction.reference') ?? $create->json('reference');

            // Étape 2 — débiter via le channel (déclenche le push USSD / la redirection carte).
            $charge = Http::withHeaders([
                'Authorization' => $this->publicKey,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/payments/{$notchRef}", [
                'channel' => $this->channel,
                'data' => ['phone' => $phoneNumber],
            ]);

            return [
                'success' => $charge->successful(),
                'external_ref' => $notchRef,
                'status' => $charge->successful() ? 'pending' : 'failed',
                'raw' => $charge->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('NotchPay initiate exception: ' . $e->getMessage());
            return $this->failure($reference, ['error' => $e->getMessage()]);
        }
    }

    public function checkStatus(string $externalRef): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->publicKey,
            'Accept' => 'application/json',
        ])->get("{$this->baseUrl}/payments/{$externalRef}");

        $data = $response->json();
        // NotchPay renvoie un statut en minuscules : complete | failed | pending | canceled.
        $status = $data['transaction']['status'] ?? ($data['status'] ?? 'pending');

        return [
            'status' => strtoupper((string) $status), // normalisé pour processCallback (COMPLETE…)
            'financial_transaction_id' => $data['transaction']['id'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $externalRef, float $amount): array
    {
        // Remboursement non géré en mode API directe pour l'instant.
        return ['status' => 'not_implemented'];
    }

    private function failure(string $reference, ?array $raw): array
    {
        return [
            'success' => false,
            'external_ref' => $reference,
            'status' => 'failed',
            'raw' => $raw ?? [],
        ];
    }
}
