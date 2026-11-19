<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mouvements de portefeuille idempotents et atomiques (verrouillage pessimiste).
 */
class WalletService
{
    /**
     * Règle un paiement de recharge en attente et crédite le portefeuille du payeur.
     * Idempotent : un paiement déjà réglé n'est jamais crédité deux fois.
     */
    public function settleRecharge(string $paymentId, array $metadata = [], string $source = 'recharge'): ?Payment
    {
        return DB::transaction(function () use ($paymentId, $metadata, $source) {
            $payment = Payment::where('id', $paymentId)->lockForUpdate()->first();
            if (!$payment || $payment->status !== 'pending') {
                return null;
            }

            $payment->update([
                'status' => 'settled',
                'settled_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], $metadata),
            ]);

            if ($payment->payer_id) {
                $wallet = Wallet::xaf()->where('user_id', $payment->payer_id)->lockForUpdate()->first();
                if ($wallet) {
                    $wallet->increment('balance', $payment->amount);
                    $balance = $wallet->fresh()->balance;
                    Transaction::create([
                        'id' => (string) Str::uuid(),
                        'wallet_id' => $wallet->id,
                        'amount' => $payment->amount,
                        'balance_after' => $balance,
                        'type' => 'credit',
                        'related_payment' => $payment->id,
                        'meta' => ['source' => $source],
                    ]);

                    $payerId = $payment->payer_id;
                    DB::afterCommit(function () use ($payment, $payerId, $balance) {
                        try {
                            event(new \App\Events\PaymentSettled($payment));
                            event(new \App\Events\BalanceUpdated($payerId, $balance, 'wallet_recharge'));
                        } catch (\Throwable $e) {
                            \Log::warning('Diffusion crédit portefeuille échouée : ' . $e->getMessage());
                        }
                    });
                }
            }

            return $payment->fresh();
        });
    }
}
