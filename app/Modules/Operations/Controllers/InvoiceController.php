<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
     * Marquer une facture comme payée.
     */
    public function markPaid(Request $request, string $id)
    {
        $invoice = Invoice::findOrFail($id);
        $user = $request->user();

        if ($invoice->client_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($invoice->status !== 'unpaid') {
            return response()->json(['message' => 'Cette facture ne peut pas être payée'], 422);
        }

        $invoice->update(['status' => 'paid']);

        return new InvoiceResource($invoice);
    }
}
