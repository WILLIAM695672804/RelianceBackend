<?php

namespace App\Modules\Kyc\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function myDocuments()
    {
        $docs = KycDocument::where('user_id', auth()->id())->get();
        $docs->transform(function ($doc) {
            $doc->url = asset('storage/' . $doc->file_path);
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

        $path = $request->file('document')->store('kyc', 'public');

        $doc = KycDocument::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'file_path' => $path,
        ]);

        return response()->json([
            'message' => 'Document KYC soumis',
            'document' => [
                'id' => $doc->id,
                'type' => $doc->type,
                'status' => $doc->status,
                'url' => asset('storage/' . $doc->file_path)
            ],
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
            $doc->url = asset('storage/' . $doc->file_path);
            return $doc;
        });

        return response()->json($docs);
    }

    public function validateDocument(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string',
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
