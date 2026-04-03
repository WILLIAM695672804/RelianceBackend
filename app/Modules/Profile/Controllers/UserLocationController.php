<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Profile\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserLocationController extends Controller
{
    /**
     * Get all locations for the authenticated user
     */
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->locations()->orderBy('is_default', 'desc')->get()
        );
    }

    /**
     * Store a new location
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:50',
            'address' => 'required|string',
            'city' => 'nullable|string',
            'is_default' => 'boolean'
        ]);

        $user = $request->user();

        // If this is set as default, unset others
        if ($request->is_default) {
            $user->locations()->update(['is_default' => false]);
        }

        $location = $user->locations()->create(array_merge($validated, [
            'id' => (string) Str::uuid()
        ]));

        return response()->json([
            'message' => 'Adresse ajoutée avec succès',
            'location' => $location
        ], 201);
    }

    /**
     * Delete a location
     */
    public function destroy(Request $request, $id)
    {
        $location = $request->user()->locations()->findOrFail($id);
        $location->delete();

        return response()->json(['message' => 'Adresse supprimée']);
    }

    /**
     * Set a location as default
     */
    public function setDefault(Request $request, $id)
    {
        $user = $request->user();
        $user->locations()->update(['is_default' => false]);
        
        $location = $user->locations()->findOrFail($id);
        $location->update(['is_default' => true]);

        return response()->json(['message' => 'Adresse par défaut mise à jour']);
    }
}
