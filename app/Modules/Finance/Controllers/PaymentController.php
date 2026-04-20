<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * GET /v1/payments — List user's payments (as payer or payee).
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $payments = Payment::where('payer_id', $userId)
            ->orWhere('payee_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return PaymentResource::collection($payments);
    }

    /**
     * GET /v1/payments/{id} — Show a single payment.
     */
    public function show(Request $request, string $id)
    {
        $userId = $request->user()->id;

        $payment = Payment::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('payer_id', $userId)
                  ->orWhere('payee_id', $userId);
            })
            ->firstOrFail();

        return new PaymentResource($payment);
    }

    /**
     * POST /v1/payments/{id}/confirm — Webhook / callback to confirm a payment.
     * In production, this would be called by the payment gateway webhook.
     */
    public function confirm(Request $request, string $id)
    {
        $payment = Payment::where('id', $id)->firstOrFail();

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Paiement déjà traité'], 409);
        }

        // Mark payment as settled
        $payment->update([
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        // If this is a wallet recharge, credit the wallet
        $meta = $payment->metadata ?? [];
        if (($meta['type'] ?? '') === 'wallet_recharge' && $payment->payer_id) {
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
                    'meta' => ['source' => 'recharge'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Paiement confirmé',
            'payment' => new PaymentResource($payment),
        ]);
    }
}
