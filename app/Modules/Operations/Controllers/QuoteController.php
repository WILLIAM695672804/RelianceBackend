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

        return response()->json([
            'status' => 'success',
            'message' => 'Devis accepté, facture générée',
            'data' => [
                'quote' => new QuoteResource($quote),
                'invoice_id' => $invoice->id,
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
