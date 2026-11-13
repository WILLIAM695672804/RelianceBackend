<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Resources\WalletResource;
use App\Modules\Finance\Resources\TransactionResource;
use App\Modules\Finance\Requests\RechargeWalletRequest;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * GET /v1/wallet — Show current user's wallet (create if absent).
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Admins don't need wallets — only clients, techniciens and agences
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Les administrateurs n\'ont pas de wallet'], 403);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => 'XAF'],
            ['id' => (string) Str::uuid(), 'balance' => 0]
        );

        return new WalletResource($wallet);
    }

    /**
     * GET /v1/wallets — Portefeuilles de l'utilisateur dans chaque devise (XAF, EUR, USD).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Les administrateurs n\'ont pas de wallet'], 403);
        }
        foreach (\App\Modules\Finance\Services\CurrencyConverter::CURRENCIES as $currency) {
            Wallet::firstOrCreate(['user_id' => $user->id, 'currency' => $currency], ['id' => (string) Str::uuid(), 'balance' => 0]);
        }

        return WalletResource::collection(Wallet::where('user_id', $user->id)->orderBy('currency')->get());
    }

    /**
     * POST /v1/wallets/convert — Convertit un montant entre deux portefeuilles de l'utilisateur.
     */
    public function convert(Request $request, \App\Modules\Finance\Services\CurrencyConverter $converter)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'from_currency' => 'required|in:XAF,EUR,USD',
            'to_currency' => 'required|in:XAF,EUR,USD|different:from_currency',
        ]);
        if ($request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Les administrateurs n\'ont pas de wallet'], 403);
        }

        try {
            return response()->json(['message' => 'Conversion effectuée', 'data' => $converter->convert($request->user()->id, (float) $data['amount'], $data['from_currency'], $data['to_currency'])]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /v1/wallet/recharge — Initiate a wallet recharge (creates pending payment).
     */
    public function recharge(RechargeWalletRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = $request->user();
            $wallet = Wallet::xaf()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

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

            // Déclenche réellement le paiement auprès de la passerelle (NotchPay).
            $gateway = PaymentGatewayFactory::make($request->method);
            $result = $gateway->initiate(
                (float) $request->amount,
                $wallet->currency,
                (string) ($request->phone_number ?? ''),
                ['payment_id' => $payment->id, 'description' => 'Recharge wallet Reliance']
            );

            $payment->update([
                'external_ref' => $result['external_ref'],
                'status' => $result['success'] ? 'pending' : 'failed',
            ]);

            if (!$result['success']) {
                return response()->json([
                    'message' => 'Échec de l\'initiation du paiement',
                    'payment_id' => $payment->id,
                    'status' => 'failed',
                ], 422);
            }

            return response()->json([
                'message' => 'Paiement initié',
                'payment_id' => $payment->id,
                'external_ref' => $result['external_ref'],
                'status' => 'pending',
                'instructions' => $result['raw']['instructions'] ?? null,
            ]);
        });
    }

    /**
     * GET /v1/wallet/transactions — Paginated transaction history.
     */
    public function transactions(Request $request)
    {
        $wallet = Wallet::xaf()->where('user_id', $request->user()->id)->firstOrFail();

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
            'method' => 'required|in:bank_transfer,momo,om,card',
            'phone_number' => 'required_if:method,momo,om|string',
        ]);

        return DB::transaction(function () use ($request) {
            $wallet = Wallet::xaf()->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();

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
        });
    }
}
