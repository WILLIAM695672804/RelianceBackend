<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Profile\Requests\UpdateTechProfileRequest;
use App\Modules\Profile\Requests\UpdateTechLocationRequest;
use App\Modules\Profile\Resources\IdentityHubResource;
use App\Modules\Auth\Models\User;
use App\Modules\Profile\Models\Location;
use Illuminate\Support\Facades\DB;

class TechnicianProfileController extends Controller
{
    public function show(Request $request, $id = null)
    {
        $userId = $id ?? $request->user()->id;
        $user = User::with(['skills', 'locations'])->findOrFail($userId);
        
        return response()->json([
            'status' => 'success',
            'data' => new IdentityHubResource($user)
        ]);
    }

    public function updateProfile(UpdateTechProfileRequest $request)
    {
        $user = $request->user();
        
        $validated = $request->validated();
        
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            
            // Delete old avatar if exists
            if ($user->avatar_url && !str_starts_with($user->avatar_url, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_url);
            }
            
            $user->avatar_url = $path; // Or full URL via Storage::url($path)
        }

        // Generate JSON meta for extended fields
        $meta = $user->meta ?? [];
        if (isset($validated['bio'])) {
            $meta['bio'] = $validated['bio'];
        }
        if (isset($validated['competences'])) {
            $meta['competences'] = $validated['competences'];
        }
        $user->meta = $meta;

        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => new IdentityHubResource($user)
        ]);
    }

    public function updateLocation(UpdateTechLocationRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $location = Location::firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            ['address' => $validated['address'] ?? null, 'city' => $validated['city'] ?? null]
        );

        $location->address = $validated['address'] ?? $location->address;
        $location->city = $validated['city'] ?? $location->city;
        
        $meta = $location->meta ?? [];
        $meta['zones'] = $validated['zones'] ?? [];
        $meta['latitude'] = $validated['latitude'];
        $meta['longitude'] = $validated['longitude'];
        $meta['radius'] = $validated['radius'] ?? 50;
        $location->meta = $meta;
        
        $location->save();

        // Spatial Query: UPDATE PostGIS point
        DB::statement(
            "UPDATE locations SET geom = ST_GeomFromText(?, 4326) WHERE id = ?",
            ["POINT({$validated['longitude']} {$validated['latitude']})", $location->id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Location and perimeter updated successfully',
            'data' => new IdentityHubResource($user)
        ]);
    }
}
