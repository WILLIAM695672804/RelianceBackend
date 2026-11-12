<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Operations\Models\Quote;
use App\Modules\Operations\Models\Invoice;
use App\Modules\Operations\Requests\StoreQuoteRequest;
use App\Modules\Operations\Resources\QuoteResource;
use App\Modules\Matching\Models\ClientRequest;

class QuoteController extends Controller
{
    /**
     * Liste des devis pour une demande client.
     */
    public function index(Request $request, string $requestId)
    {
        $clientRequest = ClientRequest::findOrFail($requestId);

        $quotes = Quote::where('request_id', $requestId)
            ->orderBy('created_at', 'desc')
            ->get();

        return QuoteResource::collection($quotes);
    }

    /**
     * Créer un devis (technicien uniquement).
     */
    public function store(StoreQuoteRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        // Seul le technicien dont la proposition a été acceptée peut chiffrer la demande.
        $mandated = \App\Modules\Matching\Models\Proposal::where('request_id', $validated['request_id'])
            ->where('technician_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
        if (!$mandated) {
            return response()->json(['message' => 'Vous n\'êtes pas mandaté sur cette demande.'], 403);
        }

        $quote = Quote::create([
            'request_id'   => $validated['request_id'],
            'issuer_id'    => $user->id,
            'total_amount' => $validated['total_amount'],
            'breakdown'    => $validated['breakdown'] ?? null,
            'status'       => 'draft',
            'valid_until'  => $validated['valid_until'] ?? null,
        ]);

        return new QuoteResource($quote);
    }

    /**
     * Voir un devis.
     */
    public function show(string $id)
    {
        $quote = Quote::with(['issuer', 'request'])->findOrFail($id);
        return new QuoteResource($quote);
    }

    /**
     * Envoyer un devis au client (passer de draft à sent).
     */
    public function send(Request $request, string $id)
    {
        $quote = Quote::findOrFail($id);
        $user = $request->user();

        if ($quote->issuer_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($quote->status !== 'draft') {
            return response()->json(['message' => 'Le devis ne peut être envoyé que depuis le statut brouillon'], 422);
        }

        $quote->update(['status' => 'sent']);

        // Broadcast to client
        $clientId = $quote->load('request')->request->client_id;
        event(new \App\Events\QuoteSubmitted($quote, $clientId));
        app(\App\Modules\Notification\Services\NotificationService::class)
            ->notifyQuoteReceived($clientId, $quote->id, $quote->request_id);

        return new QuoteResource($quote);
    }

    /**
     * Accepter un devis (client uniquement).
     * Crée automatiquement une facture.
     */
    public function accept(Request $request, string $id)
    {
        $quote = Quote::with('request')->findOrFail($id);
        $user = $request->user();

        if ($quote->request->client_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$quote->isAcceptable()) {
            return response()->json(['message' => 'Ce devis ne peut plus être accepté'], 422);
        }

        $quote->update(['status' => 'accepted']);

        // Créer la facture automatiquement
        $invoice = Invoice::create([
            'quote_id'   => $quote->id,
            'client_id'  => $user->id,
            'issuer_id'  => $quote->issuer_id,
            'amount'     => $quote->total_amount,
            'tax_amount' => 0,
            'status'     => 'unpaid',
        ]);

        // Mettre à jour le statut de la demande
        $quote->request->update(['status' => 'assigned']);

        event(new \App\Events\InvoiceCreated($invoice, $user->id));

        // Séquestre : les fonds sont réservés dès l'acceptation si le solde le permet ;
        // sinon le client règle (ou recharge) avant/après l'intervention.
        $escrow = 'insufficient_funds';
        try {
            app(\App\Modules\Finance\Services\EscrowService::class)
                ->placeHold($user->id, (float) $invoice->amount, 'invoice:' . $invoice->id);
            $escrow = 'held';
        } catch (\DomainException $e) {
            // solde insuffisant : facture laissée impayée
        }

        app(\App\Modules\Notification\Services\NotificationService::class)->send(
            $quote->issuer_id,
            'Devis accepté',
            'Votre devis a été accepté par le client.',
            ['type' => 'quote_accepted', 'quote_id' => $quote->id, 'request_id' => $quote->request_id],
            ['in_app', 'push']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Devis accepté, facture générée',
            'data' => [
                'quote' => new QuoteResource($quote),
                'invoice_id' => $invoice->id,
                'escrow' => $escrow,
            ]
        ]);
    }

    /**
     * Rejeter un devis (client uniquement).
     */
    public function reject(Request $request, string $id)
    {
        $quote = Quote::with('request')->findOrFail($id);
        $user = $request->user();

        if ($quote->request->client_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!$quote->isAcceptable()) {
            return response()->json(['message' => 'Ce devis ne peut plus être modifié'], 422);
        }

        $quote->update(['status' => 'rejected']);

        return new QuoteResource($quote);
    }
}
