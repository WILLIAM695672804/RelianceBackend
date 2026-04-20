<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use Illuminate\Http\Request;
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
     * POST /v1/credits/purchase — Purchase platform credits using exchange rate.
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'from_currency' => 'required|string|max:3',
        ]);

        $rate = ExchangeRate::where('from_currency', $request->from_currency)
            ->where('to_currency', 'XAF')
            ->where('active', true)
            ->firstOrFail();

        $creditAmount = round($request->amount * $rate->rate, 2);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['id' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'XAF']
        );

        $wallet->increment('balance', $creditAmount);

        Transaction::create([
            'id' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'amount' => $creditAmount,
            'balance_after' => $wallet->fresh()->balance,
            'type' => 'credit',
            'meta' => [
                'source' => 'credit_purchase',
                'from_currency' => $request->from_currency,
                'original_amount' => $request->amount,
                'rate' => $rate->rate,
            ],
        ]);

        return response()->json([
            'message' => "Crédits achetés: {$creditAmount} XAF",
            'credited_amount' => $creditAmount,
            'rate_used' => $rate->rate,
        ]);
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
