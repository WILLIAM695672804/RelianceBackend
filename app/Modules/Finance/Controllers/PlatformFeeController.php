<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\PlatformFee;
use Illuminate\Http\Request;

class PlatformFeeController extends Controller
{
    /**
     * GET /v1/admin/fees — List all platform fees.
     */
    public function index()
    {
        $fees = PlatformFee::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $fees]);
    }

    /**
     * POST /v1/admin/fees — Create a new platform fee.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'fee_type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'applies_to' => 'required|string|max:50',
            'meta' => 'nullable|array',
        ]);

        $fee = PlatformFee::create($request->all());

        return response()->json([
            'message' => 'Frais créé',
            'data' => $fee,
        ], 201);
    }

    /**
     * PUT /v1/admin/fees/{id} — Update a platform fee.
     */
    public function update(Request $request, string $id)
    {
        $fee = PlatformFee::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'fee_type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'applies_to' => 'sometimes|string|max:50',
            'meta' => 'nullable|array',
        ]);

        $fee->update($request->all());

        return response()->json([
            'message' => 'Frais mis à jour',
            'data' => $fee,
        ]);
    }

    /**
     * PATCH /v1/admin/fees/{id}/toggle — Toggle fee active/inactive.
     */
    public function toggle(string $id)
    {
        $fee = PlatformFee::findOrFail($id);
        $fee->update(['active' => !$fee->active]);

        return response()->json([
            'message' => $fee->active ? 'Frais activé' : 'Frais désactivé',
            'data' => $fee,
        ]);
    }
}
