<?php

namespace App\Modules\Kyc\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    public function myDocuments()
    {
        $docs = KycDocument::where('user_id', auth()->id())->get();
        $docs->transform(function ($doc) {
            $doc->url = $doc->temporaryUrl();
            return $doc;
        });
        return response()->json($docs);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('document')->store('kyc', 'local');

        $doc = KycDocument::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Document KYC soumis',
            'document' => [
                'id' => $doc->id,
                'type' => $doc->type,
                'status' => $doc->status,
                'url' => $doc->temporaryUrl()
            ],
        ]);
    }
    /**
     * GET /v1/auth/kyc/documents/{id}/fichier — fichier servi sur présentation d'une URL temporaire signée.
     */
    public function file(Request $request, string $id)
    {
        $expires = (int) $request->query('expires');
        $signature = (string) $request->query('signature');

        if ($expires < now()->getTimestamp() || !hash_equals(KycDocument::signature($id, $expires), $signature)) {
            abort(403, 'Lien expiré ou invalide.');
        }

        $doc = KycDocument::findOrFail($id);
        if (!Storage::disk('local')->exists($doc->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($doc->file_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminIndex(Request $request)
    {
        $query = KycDocument::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $docs = $query->orderBy('created_at', 'desc')->paginate(20);

        $docs->getCollection()->transform(function ($doc) {
            $doc->url = $doc->temporaryUrl();
            return $doc;
        });

        return response()->json($docs);
    }

    public function validateDocument(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            // Un rejet doit être motivé : le demandeur doit savoir quoi corriger.
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $doc = KycDocument::findOrFail($id);

        $doc->update([
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason,
            'validated_by' => auth()->id(),
        ]);

        event(new \App\Events\KycStatusChanged(
            $doc->user_id,
            $request->status,
            $request->rejection_reason
        ));

        return response()->json([
            'message' => 'KYC mis à jour',
            'document' => $doc,
        ]);
    }
}
