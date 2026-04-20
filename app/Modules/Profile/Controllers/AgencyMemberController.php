<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Profile\Models\AgencyMembership;
use App\Modules\Profile\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyMemberController extends Controller
{
    /**
     * GET /v1/agencies/{id}/members — List members of an agency.
     */
    public function index(string $id)
    {
        $agency = Agency::findOrFail($id);

        $members = AgencyMembership::where('agency_id', $id)
            ->with('user')
            ->get();

        return response()->json(['data' => $members]);
    }

    /**
     * POST /v1/agencies/{id}/members — Add a technician to the agency.
     */
    public function store(Request $request, string $id)
    {
        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'role' => 'sometimes|in:member,admin',
        ]);

        $agency = Agency::findOrFail($id);

        // Check authorization (only agency owner/admin)
        $membership = AgencyMembership::where('agency_id', $id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->first();

        if (!$membership && $agency->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Check if already member
        $existing = AgencyMembership::where('agency_id', $id)
            ->where('user_id', $request->user_id)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'L\'utilisateur est déjà membre'], 422);
        }

        $member = AgencyMembership::create([
            'id' => (string) Str::uuid(),
            'agency_id' => $id,
            'user_id' => $request->user_id,
            'role' => $request->role ?? 'member',
        ]);

        return response()->json([
            'message' => 'Membre ajouté',
            'data' => $member->load('user'),
        ], 201);
    }

    /**
     * DELETE /v1/agencies/{id}/members/{userId} — Remove a member.
     */
    public function destroy(Request $request, string $id, string $userId)
    {
        $agency = Agency::findOrFail($id);

        // Check authorization
        if ($agency->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Can't remove the owner
        if ($userId === $agency->owner_id) {
            return response()->json(['message' => 'Impossible de retirer le propriétaire'], 422);
        }

        AgencyMembership::where('agency_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['message' => 'Membre retiré']);
    }
}
