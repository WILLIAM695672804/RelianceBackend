<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPayoutController extends Controller
{
    public function pending()
    {
        $payouts = Payment::whereNotNull('payee_id')
            ->whereNull('payer_id')
            ->where('status', 'pending')
            ->with('payee')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return PaymentResource::collection($payouts);
    }

    public function all(Request $request)
    {
        $query = Payment::whereNotNull('payee_id')->whereNull('payer_id');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return PaymentResource::collection(
            $query->with('payee')->orderBy('created_at', 'desc')->paginate(20)
        );
    }

    public function approve(Request $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $payment = Payment::where('id', $id)
                ->where('status', 'pending')
                ->whereNull('payer_id')
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::xaf()->where('user_id', $payment->payee_id)->lockForUpdate()->firstOrFail();

            // Solde DISPONIBLE (hors fonds réservés en escrow), pas le solde brut,
            // pour éviter d'approuver un retrait sur des fonds déjà bloqués.
            if ($wallet->availableBalance() < $payment->amount) {
                return response()->json(['message' => 'Solde wallet insuffisant pour ce retrait'], 422);
            }

            $wallet->decrement('balance', $payment->amount);

            Transaction::create([
                'id'              => (string) Str::uuid(),
                'wallet_id'       => $wallet->id,
                'amount'          => $payment->amount,
                'balance_after'   => $wallet->fresh()->balance,
                'type'            => 'debit',
                'related_payment' => $payment->id,
                'meta'            => [
                    'source'      => 'withdrawal_approved',
                    'approved_by' => $request->user()->id,
                ],
            ]);

            $payment->update([
                'status'     => 'settled',
                'settled_at' => now(),
                'metadata'   => array_merge($payment->metadata ?? [], [
                    'approved_by' => $request->user()->id,
                    'approved_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json([
                'message' => 'Retrait approuvé et traité',
                'data'    => new PaymentResource($payment->fresh()),
            ]);
        });
    }

    public function reject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $payment = Payment::where('id', $id)
            ->where('status', 'pending')
            ->whereNull('payer_id')
            ->firstOrFail();

        $payment->update([
            'status'   => 'rejected',
            'metadata' => array_merge($payment->metadata ?? [], [
                'rejected_by' => $request->user()->id,
                'rejected_at' => now()->toIso8601String(),
                'reject_reason' => $request->reason,
            ]),
        ]);

        return response()->json([
            'message' => 'Retrait refusé',
            'data'    => new PaymentResource($payment->fresh()),
        ]);
    }

    public function stats()
    {
        $withdrawals = Payment::whereNotNull('payee_id')->whereNull('payer_id');

        return response()->json([
            'pending_count'  => (clone $withdrawals)->where('status', 'pending')->count(),
            'pending_amount' => (clone $withdrawals)->where('status', 'pending')->sum('amount'),
            'settled_count'  => (clone $withdrawals)->where('status', 'settled')->count(),
            'settled_amount' => (clone $withdrawals)->where('status', 'settled')->sum('amount'),
            'rejected_count' => (clone $withdrawals)->where('status', 'rejected')->count(),
        ]);
    }
}
