<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * POST /v1/webhooks/momo — MTN MOMO callback.
     */
    public function momo(Request $request)
    {
        Log::info('MOMO Webhook received', $request->all());

        $externalRef = $request->input('externalId') ?? $request->input('referenceId');
        if (!$externalRef) {
            return response()->json(['message' => 'Missing reference'], 400);
        }

        return $this->processCallback($externalRef, 'momo', $request->all());
    }

    /**
     * POST /v1/webhooks/om — Orange Money callback.
     */
    public function orangeMoney(Request $request)
    {
        Log::info('Orange Money Webhook received', $request->all());

        $externalRef = $request->input('order_id') ?? $request->input('reference');
        if (!$externalRef) {
            return response()->json(['message' => 'Missing reference'], 400);
        }

        return $this->processCallback($externalRef, 'om', $request->all());
    }

    /**
     * Process a payment callback: settle the payment and credit the wallet.
     */
    private function processCallback(string $externalRef, string $method, array $rawData)
    {
        $payment = Payment::where('external_ref', $externalRef)->first();

        if (!$payment) {
            // Try to find by metadata
            $payment = Payment::where('status', 'pending')
                ->where('method', $method)
                ->whereJsonContains('metadata->type', 'wallet_recharge')
                ->latest()
                ->first();
        }

        if (!$payment || $payment->status !== 'pending') {
            return response()->json(['message' => 'Payment not found or already processed'], 404);
        }

        // Verify with gateway
        try {
            $gateway = PaymentGatewayFactory::make($method);
            $status = $gateway->checkStatus($externalRef);

            if (in_array($status['status'], ['SUCCESSFUL', 'SUCCESS', 'COMPLETED'])) {
                $payment->update([
                    'status' => 'settled',
                    'external_ref' => $externalRef,
                    'metadata' => array_merge($payment->metadata ?? [], ['gateway_response' => $rawData]),
                ]);

                // Credit wallet
                if ($payment->payer_id) {
                    $wallet = Wallet::where('user_id', $payment->payer_id)->first();
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
                    }
                }

                return response()->json(['message' => 'Payment confirmed']);
            }
        } catch (\Exception $e) {
            Log::error("Payment gateway verification failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Payment verification pending'], 202);
    }
}
