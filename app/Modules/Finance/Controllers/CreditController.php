<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditController extends Controller
{
    /**
     * GET /v1/credits/rates — Get active exchange rates.
     */
    public function rates()
    {
        $rates = ExchangeRate::where('active', true)->get();
        return response()->json(['data' => $rates]);
    }

    /**
     * POST /v1/credits/purchase — Achat de crédits via la passerelle de paiement.
     * Le montant en devise étrangère est converti en XAF, puis encaissé via NotchPay.
     * Le wallet n'est crédité qu'à la confirmation du webhook (aucun crédit gratuit).
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'from_currency' => 'required|string|max:3',
            'method' => 'required|in:card,bank_transfer,momo,om',
            'phone_number' => 'required_if:method,momo,om|string',
        ]);

        $rate = ExchangeRate::where('from_currency', $request->from_currency)
            ->where('to_currency', 'XAF')
            ->where('active', true)
            ->firstOrFail();

        // XAF est une devise sans décimales : NotchPay rejette tout montant fractionnaire.
        $creditAmount = (float) round($request->amount * $rate->rate);

        return DB::transaction(function () use ($request, $rate, $creditAmount) {
            // Garantit l'existence du wallet (crédité plus tard par le webhook).
            Wallet::firstOrCreate(
                ['user_id' => $request->user()->id],
                ['id' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'XAF']
            );

            // Paiement EN ATTENTE : aucun crédit tant que la passerelle n'a pas confirmé.
            $payment = Payment::create([
                'payer_id' => $request->user()->id,
                'payee_id' => null,
                'method'   => $request->method,
                'amount'   => $creditAmount,
                'currency' => 'XAF',
                'status'   => 'pending',
                'metadata' => [
                    'type' => 'credit_purchase',
                    'from_currency' => $request->from_currency,
                    'original_amount' => (float) $request->amount,
                    'rate' => $rate->rate,
                    'phone_number' => $request->phone_number ?? null,
                ],
            ]);

            $gateway = PaymentGatewayFactory::make($request->method);
            $result = $gateway->initiate(
                (float) $creditAmount,
                'XAF',
                (string) ($request->phone_number ?? ''),
                ['payment_id' => $payment->id, 'description' => 'Achat de crédits Reliance']
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
                'message' => 'Paiement initié — crédits ajoutés après confirmation',
                'payment_id' => $payment->id,
                'credit_amount' => $creditAmount,
                'rate_used' => $rate->rate,
                'status' => 'pending',
            ]);
        });
    }

    // ── Admin endpoints ──────────────────────────────────────

    /**
     * GET /v1/admin/credits/rates — All exchange rates (including inactive).
     */
    public function adminRates()
    {
        $rates = ExchangeRate::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $rates]);
    }

    /**
     * POST /v1/admin/credits/rates — Set a new exchange rate.
     */
    public function setRate(Request $request)
    {
        $request->validate([
            'from_currency' => 'required|string|max:3',
            'to_currency' => 'required|string|max:3',
            'rate' => 'required|numeric|min:0.0001',
        ]);

        // Deactivate current rate for this pair
        ExchangeRate::where('from_currency', $request->from_currency)
            ->where('to_currency', $request->to_currency)
            ->where('active', true)
            ->update(['active' => false]);

        $rate = ExchangeRate::create([
            'from_currency' => $request->from_currency,
            'to_currency' => $request->to_currency,
            'rate' => $request->rate,
            'active' => true,
            'set_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Taux de change défini',
            'data' => $rate,
        ], 201);
    }
}
