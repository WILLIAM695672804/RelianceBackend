<?php

namespace App\Modules\Kyc\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $path = $request->file('document')->store('kyc');

        $doc = KycDocument::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'file_path' => $path,
        ]);

        return response()->json([
            'message' => 'Document KYC soumis',
            'document' => $doc,
        ]);
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

        return response()->json([
            'message' => 'KYC mis à jour',
            'document' => $doc,
        ]);
    }
}
