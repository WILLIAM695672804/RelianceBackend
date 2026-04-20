<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Resources\WalletResource;
use App\Modules\Finance\Resources\TransactionResource;
use App\Modules\Finance\Requests\RechargeWalletRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * GET /v1/wallet — Show current user's wallet (create if absent).
     */
    public function show(Request $request)
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['id' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'XAF']
        );

        return new WalletResource($wallet);
    }

    /**
     * POST /v1/wallet/recharge — Initiate a wallet recharge (creates pending payment).
     */
    public function recharge(RechargeWalletRequest $request)
    {
        $user = $request->user();
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();

        $payment = Payment::create([
            'payer_id' => $user->id,
            'payee_id' => null,
            'method' => $request->method,
            'amount' => $request->amount,
            'currency' => $wallet->currency,
            'status' => 'pending',
            'metadata' => [
                'type' => 'wallet_recharge',
                'phone_number' => $request->phone_number ?? null,
            ],
        ]);

        return response()->json([
            'message' => 'Paiement initié',
            'payment_id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    /**
     * GET /v1/wallet/transactions — Paginated transaction history.
     */
    public function transactions(Request $request)
    {
        $wallet = Wallet::where('user_id', $request->user()->id)->firstOrFail();

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    /**
     * POST /v1/wallet/withdraw — Request a withdrawal (technician).
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:500',
            'method' => 'required|in:bank_transfer,momo,om',
            'phone_number' => 'required_if:method,momo,om|string',
        ]);

        $wallet = Wallet::where('user_id', $request->user()->id)->firstOrFail();

        if ($wallet->availableBalance() < $request->amount) {
            return response()->json(['message' => 'Solde insuffisant'], 422);
        }

        $payment = Payment::create([
            'payer_id' => null,
            'payee_id' => $request->user()->id,
            'method' => $request->method,
            'amount' => $request->amount,
            'currency' => $wallet->currency,
            'status' => 'pending',
            'metadata' => [
                'type' => 'withdrawal',
                'phone_number' => $request->phone_number ?? null,
            ],
        ]);

        return response()->json([
            'message' => 'Demande de retrait enregistrée',
            'payment_id' => $payment->id,
        ]);
    }
}
