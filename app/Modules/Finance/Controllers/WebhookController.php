<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /** Correspondance channel NotchPay → method interne (pour reconstruire la passerelle). */
    private const CHANNEL_TO_METHOD = [
        'cm.mtn' => 'momo',
        'cm.orange' => 'om',
        'card' => 'card',
    ];

    /**
     * POST /v1/webhooks/notchpay — Callback NotchPay (agrégateur MoMo / Orange / carte).
     */
    public function notchpay(Request $request)
    {
        // Seules des métadonnées sont journalisées : la charge utile contient des données de paiement.
        Log::info('NotchPay webhook reçu', [
            'reference' => $request->input('data.reference') ?? $request->input('reference'),
            'event' => $request->input('event'),
            'ip' => $request->ip(),
        ]);

        // Vérification de la signature HMAC. Échec fermé hors développement : sans clé de hachage,
        // n'importe qui pourrait déclencher le rapprochement d'un paiement.
        $hashKey = config('services.notchpay.hash_key');
        if (!$hashKey && !app()->environment('local', 'testing')) {
            Log::critical('NotchPay webhook: NOTCHPAY_HASH_KEY absente, callback refusé');
            return response()->json(['message' => 'Webhook non configuré'], 503);
        }
        if ($hashKey) {
            $expected = hash_hmac('sha256', $request->getContent(), $hashKey);
            $provided = $request->header('x-notch-signature', '');
            if (!hash_equals($expected, (string) $provided)) {
                Log::warning('NotchPay webhook: signature invalide', ['ip' => $request->ip()]);
                return response()->json(['message' => 'Signature invalide'], 401);
            }
        }

        $externalRef = $request->input('data.reference')
            ?? $request->input('reference')
            ?? $request->input('data.merchant_reference');
        if (!$externalRef) {
            return response()->json(['message' => 'Missing reference'], 400);
        }

        $channel = $request->input('data.channel');
        $method = self::CHANNEL_TO_METHOD[$channel] ?? 'momo';

        return $this->processCallback($externalRef, $method, $request->all());
    }

    /**
     * Process a payment callback: settle the payment and credit the wallet.
     */
    private function processCallback(string $externalRef, string $method, array $rawData)
    {
        $payment = Payment::where('external_ref', $externalRef)->first();

        if (!$payment || $payment->status !== 'pending') {
            return response()->json(['message' => 'Payment not found or already processed'], 404);
        }

        // Verify with gateway
        try {
            $gateway = PaymentGatewayFactory::make($method);
            $status = $gateway->checkStatus($externalRef);

            if (in_array($status['status'], ['SUCCESSFUL', 'SUCCESS', 'COMPLETED', 'COMPLETE'])) {
                $settled = app(\App\Modules\Finance\Services\WalletService::class)
                    ->settleRecharge($payment->id, ['gateway_response' => $rawData, 'method' => $method], 'gateway_callback');

                return $settled
                    ? response()->json(['message' => 'Payment confirmed'])
                    : response()->json(['message' => 'Payment already processed'], 409);
            }
        } catch (\Exception $e) {
            Log::error("Payment gateway verification failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Payment verification pending'], 202);
    }
}
