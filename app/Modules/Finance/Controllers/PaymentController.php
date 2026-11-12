<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Models\Transaction;
use App\Modules\Finance\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Services\WalletService;
use App\Modules\Finance\Services\PaymentGateway\PaymentGatewayFactory;

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
     * POST /v1/payments/{id}/confirm — Confirmation d'un paiement en attente.
     *
     * - virement bancaire : confirmation manuelle, réservée à l'administrateur ;
     * - MoMo / OM / carte : l'état est vérifié auprès de la passerelle, jamais déclaré par l'appelant.
     */
    public function confirm(Request $request, string $id, WalletService $wallets)
    {
        $user = $request->user();
        $payment = Payment::findOrFail($id);

        if ($payment->payer_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Paiement déjà traité'], 409);
        }

        if ($payment->method === 'bank_transfer') {
            if (!$user->hasRole('admin')) {
                return response()->json(['message' => 'Un virement est confirmé par un administrateur après vérification.'], 403);
            }
        } else {
            try {
                $status = PaymentGatewayFactory::make($payment->method)->checkStatus((string) $payment->external_ref);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Vérification auprès de la passerelle impossible.'], 502);
            }
            if (!in_array($status['status'], ['SUCCESSFUL', 'SUCCESS', 'COMPLETED', 'COMPLETE'], true)) {
                return response()->json(['message' => 'Paiement non encore confirmé par la passerelle.', 'status' => $status['status']], 202);
            }
        }

        $settled = $wallets->settleRecharge($payment->id, [], 'confirmation');
        if (!$settled) {
            return response()->json(['message' => 'Paiement déjà traité'], 409);
        }

        return response()->json([
            'message' => 'Paiement confirmé',
            'payment' => new PaymentResource($settled),
        ]);
    }
}
