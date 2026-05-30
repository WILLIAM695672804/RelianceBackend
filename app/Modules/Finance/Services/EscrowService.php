<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Hold;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Models\PlatformFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EscrowService
{
    /**
     * Place a hold (escrow) on a user's wallet.
     */
    public function placeHold(string $userId, float $amount, string $reason): Hold
    {
        return DB::transaction(function () use ($userId, $amount, $reason) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($wallet->availableBalance() < $amount) {
                throw new \DomainException('Solde insuffisant');
            }

            return Hold::create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'reason' => $reason,
                'status' => 'held',
            ]);
        });
    }

    /**
     * Capture a hold — debit payer, credit payee (minus platform fees), mark hold as captured.
     */
    public function captureHold(string $holdId, string $payeeId, string $method): Payment
    {
        return DB::transaction(function () use ($holdId, $payeeId, $method) {
            $hold = Hold::where('id', $holdId)->lockForUpdate()->firstOrFail();
            if ($hold->status !== 'held') {
                throw new \DomainException('Hold not in held state');
            }

            $payerWallet = Wallet::where('id', $hold->wallet_id)->lockForUpdate()->firstOrFail();
            $payeeWallet = Wallet::firstOrCreate(
                ['user_id' => $payeeId],
                ['id' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'XAF']
            );
            $payeeWallet = Wallet::where('user_id', $payeeId)->lockForUpdate()->firstOrFail();

            // Calculate platform fees
            $fees = PlatformFee::where('active', true)
                ->where('applies_to', 'job')
                ->get()
                ->sum(fn ($fee) => $fee->fee_type === 'percentage'
                    ? $hold->amount * $fee->value
                    : $fee->value);

            $netAmount = $hold->amount - $fees;

            $payment = Payment::create([
                'payer_id' => $payerWallet->user_id,
                'payee_id' => $payeeId,
                'method' => $method,
                'amount' => $hold->amount,
                'currency' => $payerWallet->currency,
                'status' => 'settled',
                'metadata' => ['fees' => $fees, 'net_amount' => $netAmount],
            ]);

            // Debit payer
            $payerWallet->decrement('balance', $hold->amount);
            Transaction::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $payerWallet->id,
                'amount' => -$hold->amount,
                'balance_after' => $payerWallet->fresh()->balance,
                'type' => 'debit',
                'related_payment' => $payment->id,
            ]);

            // Credit payee
            $payeeWallet->increment('balance', $netAmount);
            Transaction::create([
                'id' => (string) Str::uuid(),
                'wallet_id' => $payeeWallet->id,
                'amount' => $netAmount,
                'balance_after' => $payeeWallet->fresh()->balance,
                'type' => 'credit',
                'related_payment' => $payment->id,
            ]);

            $hold->update([
                'status' => 'captured',
                'released_at' => now(),
                'related_payment' => $payment->id,
            ]);

            // Diffusion temps-réel APRÈS commit, et best-effort : un serveur websockets
            // indisponible ne doit jamais annuler l'opération financière (rollback) ni
            // faire échouer la requête.
            $payerId = $payerWallet->user_id;
            $payerBalance = $payerWallet->fresh()->balance;
            $payeeBalance = $payeeWallet->fresh()->balance;
            $holdAmount = $hold->amount;
            DB::afterCommit(function () use ($payerId, $payeeId, $holdAmount, $netAmount, $payerBalance, $payeeBalance) {
                try {
                    event(new \App\Events\HoldCaptured($payerId, $payeeId, $holdAmount, $netAmount));
                    event(new \App\Events\BalanceUpdated($payerId, $payerBalance, 'escrow_captured'));
                    event(new \App\Events\BalanceUpdated($payeeId, $payeeBalance, 'escrow_received'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Broadcast escrow capture échoué: ' . $e->getMessage());
                }
            });

            return $payment;
        });
    }

    /**
     * Release a hold without capturing (e.g. cancellation).
     */
    public function releaseHold(string $holdId): Hold
    {
        return DB::transaction(function () use ($holdId) {
            $hold = Hold::where('id', $holdId)->lockForUpdate()->firstOrFail();
            if ($hold->status !== 'held') {
                throw new \DomainException('Hold not in held state');
            }

            $hold->update(['status' => 'released', 'released_at' => now()]);

            $wallet = \App\Modules\Finance\Models\Wallet::where('id', $hold->wallet_id)->first();
            if ($wallet) {
                $userId = $wallet->user_id;
                $balance = $wallet->fresh()->balance;
                $amount = $hold->amount;
                DB::afterCommit(function () use ($userId, $balance, $amount) {
                    try {
                        event(new \App\Events\HoldReleased($userId, $amount));
                        event(new \App\Events\BalanceUpdated($userId, $balance, 'hold_released'));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Broadcast hold release échoué: ' . $e->getMessage());
                    }
                });
            }

            return $hold->fresh();
        });
    }
}
