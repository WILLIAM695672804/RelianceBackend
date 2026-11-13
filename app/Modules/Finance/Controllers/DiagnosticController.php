<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Diagnostic;
use App\Modules\Finance\Services\EscrowService;
use App\Modules\Finance\Services\PlatformFeeService;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Frais de diagnostic (cahier §4.4) : le technicien mandaté fixe les frais de déplacement et de
 * diagnostic ; le client les règle depuis son portefeuille, avant ou après l'intervention.
 */
class DiagnosticController extends Controller
{
    private function technicianOf(ClientRequest $request): ?string
    {
        return Proposal::where('request_id', $request->id)->where('status', 'accepted')->value('technician_id');
    }

    private function present(Diagnostic $d): array
    {
        $fees = app(PlatformFeeService::class)->breakdown((float) $d->amount, 'diagnostic');

        return [
            'id' => $d->id, 'request_id' => $d->request_id, 'amount' => (float) $d->amount,
            'platform_fee' => $fees['fees'], 'net_to_technician' => $fees['net'],
            'paid' => (bool) $d->paid, 'paid_at' => $d->paid_at?->toISOString(),
        ];
    }

    /** POST /v1/requests/{id}/diagnostic — le technicien mandaté fixe les frais de diagnostic. */
    public function store(Request $request, string $id)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:100|max:10000000']);
        $demande = ClientRequest::findOrFail($id);

        if ($this->technicianOf($demande) !== $request->user()->id) {
            return response()->json(['message' => 'Seul le technicien mandaté peut fixer les frais de diagnostic.'], 403);
        }
        if (Diagnostic::where('request_id', $demande->id)->exists()) {
            return response()->json(['message' => 'Des frais de diagnostic existent déjà pour cette demande.'], 409);
        }

        $diagnostic = Diagnostic::create(['request_id' => $demande->id, 'amount' => $data['amount']]);

        app(NotificationService::class)->send(
            $demande->client_id, 'Frais de diagnostic',
            'Votre technicien a fixé des frais de diagnostic de ' . number_format((float) $data['amount'], 0, ',', ' ') . ' XAF.',
            ['type' => 'diagnostic_fee', 'request_id' => $demande->id, 'diagnostic_id' => $diagnostic->id], ['in_app', 'push']
        );

        return response()->json(['data' => $this->present($diagnostic)], 201);
    }

    /** GET /v1/requests/{id}/diagnostic — visible du client, du technicien mandaté et de l'administrateur. */
    public function show(Request $request, string $id)
    {
        $demande = ClientRequest::findOrFail($id);
        $user = $request->user();
        abort_unless($user->hasRole('admin') || $demande->client_id === $user->id || $this->technicianOf($demande) === $user->id, 403);

        $diagnostic = Diagnostic::where('request_id', $demande->id)->first();

        return response()->json(['data' => $diagnostic ? $this->present($diagnostic) : null]);
    }

    /** POST /v1/diagnostics/{id}/pay — le client règle les frais depuis son portefeuille. */
    public function pay(Request $request, string $id, EscrowService $escrow)
    {
        $diagnostic = Diagnostic::findOrFail($id);
        $demande = ClientRequest::findOrFail($diagnostic->request_id);

        if ($demande->client_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        $technicianId = $this->technicianOf($demande);
        if (!$technicianId) {
            return response()->json(['message' => 'Aucun technicien mandaté.'], 422);
        }

        try {
            DB::transaction(function () use ($diagnostic, $escrow, $request, $technicianId) {
                $locked = Diagnostic::where('id', $diagnostic->id)->lockForUpdate()->firstOrFail();
                if ($locked->paid) {
                    throw new \LogicException('already_paid');
                }
                $hold = $escrow->placeHold($request->user()->id, (float) $locked->amount, 'diagnostic:' . $locked->id);
                $payment = $escrow->captureHold($hold->id, $technicianId, 'wallet', 'diagnostic');
                $locked->update(['paid' => true, 'paid_at' => now(), 'payment_id' => $payment->id]);
            });
        } catch (\DomainException $e) { // à intercepter avant LogicException, dont elle hérite
            return response()->json(['message' => 'Solde insuffisant : rechargez votre portefeuille.'], 402);
        } catch (\LogicException $e) {
            return response()->json(['message' => 'Frais de diagnostic déjà réglés.'], 409);
        }

        return response()->json(['message' => 'Frais de diagnostic réglés', 'data' => $this->present($diagnostic->fresh())]);
    }
}
