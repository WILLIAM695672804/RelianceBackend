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
        Log::info('NotchPay Webhook received', $request->all());

        // Vérification de la signature HMAC (si une clé de hachage est configurée).
        $hashKey = config('services.notchpay.hash_key');
        if ($hashKey) {
            $expected = hash_hmac('sha256', $request->getContent(), $hashKey);
            $provided = $request->header('x-notch-signature', '');
            if (!hash_equals($expected, (string) $provided)) {
                Log::warning('NotchPay webhook: signature invalide');
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
                return DB::transaction(function () use ($payment, $externalRef, $rawData, $method) {
                    $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

                    if ($payment->status !== 'pending') {
                        return response()->json(['message' => 'Payment already processed'], 409);
                    }

                    $payment->update([
                        'status' => 'settled',
                        'settled_at' => now(),
                        'external_ref' => $externalRef,
                        'metadata' => array_merge($payment->metadata ?? [], ['gateway_response' => $rawData]),
                    ]);

                    // Credit wallet
                    if ($payment->payer_id) {
                        $wallet = Wallet::where('user_id', $payment->payer_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->increment('balance', $payment->amount);
                            Transaction::create([
                                'id' => (string) Str::uuid(),
                                'wallet_id' => $wallet->id,
                                'amount' => $payment->amount,
                                'balance_after' => $wallet->fresh()->balance,
                                'type' => 'credit',
                                'related_payment' => $payment->id,
                                'meta' => ['source' => 'gateway_callback', 'method' => $method],
                            ]);

                            // Diffusion temps-réel APRÈS commit et best-effort :
                            // un websockets indisponible ne doit pas annuler le crédit.
                            $payerId = $payment->payer_id;
                            $balance = $wallet->fresh()->balance;
                            $settledPayment = $payment;
                            DB::afterCommit(function () use ($settledPayment, $payerId, $balance) {
                                try {
                                    event(new \App\Events\PaymentSettled($settledPayment));
                                    event(new \App\Events\BalanceUpdated($payerId, $balance, 'wallet_recharge'));
                                } catch (\Throwable $e) {
                                    Log::warning('Broadcast crédit wallet échoué: ' . $e->getMessage());
                                }
                            });
                        }
                    }

                    return response()->json(['message' => 'Payment confirmed']);
                });
            }
        } catch (\Exception $e) {
            Log::error("Payment gateway verification failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Payment verification pending'], 202);
    }
}
