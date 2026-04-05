<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Profile\Resources\TechnicianCardResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicianSearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'skill' => 'nullable|string',
            'category' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:500',
            'search' => 'nullable|string|max:100',
        ]);

        $query = User::role('technicien')
            ->where('verified', true)
            ->where('is_active', true)
            ->with(['skills', 'locations']);

        // Filter by skill slug
        if ($request->filled('skill')) {
            $query->whereHas('skills', fn($q) => $q->where('slug', $request->skill));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('skills', fn($q) => $q->where('category', $request->category));
        }

        // Filter by name search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Geospatial filter + distance calculation
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radiusKm = $request->input('radius_km', 50);
            $radiusMeters = $radiusKm * 1000;

            $query->whereHas('locations', function ($q) use ($lat, $lng, $radiusMeters) {
                $q->where('is_default', true)
                  ->whereRaw(
                      'ST_DWithin(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                      [$lng, $lat, $radiusMeters]
                  );
            });

            // Add distance as a subselect
            $query->addSelect([
                'users.*',
                DB::raw("(
                    SELECT ST_Distance(
                        l.geom,
                        ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography
                    ) / 1000
                    FROM locations l
                    WHERE l.user_id = users.id AND l.is_default = true
                    LIMIT 1
                ) as distance_km")
            ]);

            $query->orderBy('distance_km', 'asc');
        } else {
            $query->orderBy('rating', 'desc');
        }

        $technicians = $query->paginate(20);

        return TechnicianCardResource::collection($technicians);
    }
}
