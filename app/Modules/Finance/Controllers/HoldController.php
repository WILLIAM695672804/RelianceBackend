<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Hold;
use App\Modules\Finance\Models\Wallet;
use App\Modules\Finance\Resources\HoldResource;
use App\Modules\Finance\Services\EscrowService;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    protected EscrowService $escrow;

    public function __construct(EscrowService $escrow)
    {
        $this->escrow = $escrow;
    }

    /**
     * GET /v1/holds — List active holds for the current user's wallet.
     */
    public function index(Request $request)
    {
        $wallet = Wallet::where('user_id', $request->user()->id)->first();

        if (!$wallet) {
            return response()->json(['data' => []]);
        }

        $holds = Hold::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return HoldResource::collection($holds);
    }

    /**
     * POST /v1/holds — Place a new hold (escrow).
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'reason' => 'required|string|max:255',
        ]);

        try {
            $hold = $this->escrow->placeHold(
                $request->user()->id,
                $request->amount,
                $request->reason
            );

            return response()->json([
                'message' => 'Hold placé avec succès',
                'data' => new HoldResource($hold),
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /v1/holds/{id}/capture — Capture a hold (pay the technician).
     */
    public function capture(Request $request, string $id)
    {
        $request->validate([
            'payee_id' => 'required|uuid',
            'method' => 'required|string',
        ]);

        // Autorisation : seul le propriétaire du wallet portant le hold (ou un admin)
        // peut capturer l'escrow. Sinon n'importe qui pourrait vider le wallet d'autrui.
        $hold = Hold::with('wallet')->findOrFail($id);
        $user = $request->user();
        if (!$hold->wallet || ($hold->wallet->user_id !== $user->id && !$user->hasRole('admin'))) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        try {
            $payment = $this->escrow->captureHold($id, $request->payee_id, $request->method);

            return response()->json([
                'message' => 'Hold capturé, paiement effectué',
                'payment_id' => $payment->id,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /v1/holds/{id}/release — Release a hold (cancellation).
     */
    public function release(Request $request, string $id)
    {
        // Autorisation : seul le propriétaire du wallet portant le hold (ou un admin)
        // peut libérer l'escrow.
        $existing = Hold::with('wallet')->findOrFail($id);
        $user = $request->user();
        if (!$existing->wallet || ($existing->wallet->user_id !== $user->id && !$user->hasRole('admin'))) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        try {
            $hold = $this->escrow->releaseHold($id);

            return response()->json([
                'message' => 'Hold libéré',
                'data' => new HoldResource($hold),
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
