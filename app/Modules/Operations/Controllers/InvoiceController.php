<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Models\Hold;
use App\Modules\Finance\Services\EscrowService;
use App\Modules\Operations\Models\Invoice;
use App\Modules\Operations\Resources\InvoiceResource;

class InvoiceController extends Controller
{
    /**
     * Lister les factures de l'utilisateur connecté.
     * Technicien: factures émises. Client: factures reçues.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::where('client_id', $user->id)
            ->orWhere('issuer_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Voir une facture.
     */
    /** GET /v1/invoices/{id}/pdf — Facture au format PDF (client, prestataire ou administrateur). */
    public function pdf(Request $request, string $id, \App\Modules\Operations\Services\InvoicePdfService $pdf)
    {
        $user = $request->user();
        $invoice = Invoice::with(['quote', 'client', 'issuer'])->findOrFail($id);

        if ($invoice->client_id !== $user->id && $invoice->issuer_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $contenu = $pdf->render($invoice);
        $chemin = 'invoices/' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $invoice->reference) . '.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($chemin, $contenu);
        if ($invoice->external_pdf_ref !== $chemin) {
            $invoice->forceFill(['external_pdf_ref' => $chemin])->save();
        }

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="facture-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $invoice->reference) . '.pdf"',
        ]);
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $invoice = Invoice::with(['quote', 'client', 'issuer'])->findOrFail($id);

        if ($invoice->client_id !== $user->id && $invoice->issuer_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return new InvoiceResource($invoice);
    }

    /**
     * POST /v1/invoices/{id}/pay — Règle la facture depuis le portefeuille du client.
     *
     * Les fonds sont d'abord placés sous séquestre (hold) s'ils ne l'ont pas été à
     * l'acceptation du devis, puis capturés au profit de l'émetteur (commission déduite).
     * Un solde insuffisant renvoie 402 : le client recharge (MoMo, OM, carte, virement) puis réessaie.
     */
    public function markPaid(Request $request, string $id, EscrowService $escrow)
    {
        $invoice = Invoice::findOrFail($id);
        $user = $request->user();

        if ($invoice->client_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($invoice->status !== 'unpaid') {
            return response()->json(['message' => 'Cette facture ne peut pas être payée'], 422);
        }

        try {
            $result = DB::transaction(function () use ($invoice, $user, $escrow) {
                $hold = Hold::whereHas('wallet', fn ($q) => $q->where('user_id', $user->id))
                    ->where('reason', 'invoice:' . $invoice->id)
                    ->where('status', 'held')
                    ->lockForUpdate()
                    ->first();

                $hold ??= $escrow->placeHold($user->id, (float) $invoice->amount, 'invoice:' . $invoice->id);
                $payment = $escrow->captureHold($hold->id, $invoice->issuer_id, 'wallet');
                $invoice->update(['status' => 'paid']);

                return $payment;
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage() . ' — rechargez votre portefeuille.'], 402);
        }

        return (new InvoiceResource($invoice->fresh()))->additional(['payment_id' => $result->id]);
    }
}
