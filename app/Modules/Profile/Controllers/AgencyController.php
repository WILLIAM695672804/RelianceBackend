<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Profile\Models\Agency;
use App\Modules\Profile\Models\AgencyMembership;
use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    /**
     * GET /v1/agencies — List all agencies.
     */
    public function index(Request $request)
    {
        $agencies = Agency::withCount('members')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($agencies);
    }

    /**
     * POST /v1/agencies — Create an agency.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $agency = Agency::create([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'owner_id' => $request->user()->id,
            'description' => $request->description,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        // Add owner as admin member
        AgencyMembership::create([
            'id' => (string) Str::uuid(),
            'agency_id' => $agency->id,
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'Agence créée',
            'data' => $agency,
        ], 201);
    }

    /**
     * GET /v1/agencies/{id} — Agency detail.
     */
    public function show(string $id)
    {
        $agency = Agency::with(['members.user', 'owner'])
            ->findOrFail($id);

        return response()->json(['data' => $agency]);
    }

    /**
     * PUT /v1/agencies/{id} — Update agency.
     */
    public function update(Request $request, string $id)
    {
        $agency = Agency::findOrFail($id);

        // Only owner can update
        if ($agency->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $agency->update($request->only(['name', 'description', 'address', 'phone', 'email']));

        return response()->json([
            'message' => 'Agence mise à jour',
            'data' => $agency,
        ]);
    }

    /**
     * GET /v1/my-agency — Get current user's agency (if agency admin).
     */
    public function myAgency(Request $request)
    {
        $membership = AgencyMembership::where('user_id', $request->user()->id)->first();

        if (!$membership) {
            return response()->json(['data' => null, 'message' => 'Vous n\'appartenez à aucune agence']);
        }

        $agency = Agency::with(['members.user'])->find($membership->agency_id);

        return response()->json(['data' => $agency]);
    }
}
