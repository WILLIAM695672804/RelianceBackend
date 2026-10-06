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

        // La table `agencies` ne porte pas de propriétaire : l'appartenance administrateur
        // (créée juste après) en tient lieu. Les coordonnées vivent dans `contact_*`.
        $agency = Agency::create([
            'id' => (string) Str::uuid(),
            'name' => $request->name,
            'description' => $request->description,
            'address' => $request->address,
            'contact_phone' => $request->phone,
            'contact_email' => $request->email,
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
        // `members` est un belongsToMany(User) : la relation imbriquée `members.user` n'existe pas.
        $agency = Agency::with(['members'])
            ->findOrFail($id);

        return response()->json(['data' => $agency]);
    }

    /**
     * PUT /v1/agencies/{id} — Update agency.
     */
    public function update(Request $request, string $id)
    {
        $agency = Agency::findOrFail($id);

        // Seul un administrateur de l'agence peut la modifier.
        if (!$agency->isAdmin($request->user()->id)) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);

        $agency->update(array_filter([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
            'contact_phone' => $request->input('phone'),
            'contact_email' => $request->input('email'),
        ], fn ($value) => $value !== null));

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

        $agency = Agency::with(['members'])->find($membership->agency_id);

        return response()->json(['data' => $agency]);
    }
}
