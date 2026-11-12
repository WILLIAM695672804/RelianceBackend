<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMatchingController extends Controller
{
    /**
     * GET /v1/admin/requests — All client requests (filterable).
     */
    public function index(Request $request)
    {
        $query = ClientRequest::with(['user', 'proposals.technician']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($requests);
    }

    /**
     * POST /v1/admin/requests/{id}/assign — Manually assign a technician to a request.
     */
    public function assign(Request $request, string $id)
    {
        $request->validate([
            'technician_id' => 'required|uuid|exists:users,id',
        ]);

        $clientRequest = ClientRequest::findOrFail($id);

        $proposal = Proposal::create([
            'request_id' => $clientRequest->id,
            'technician_id' => $request->technician_id,
            'status' => 'accepted',
            'message' => 'Assignation manuelle par l\'administrateur',
            'price' => 0,
            'expires_at' => now()->addHours(24),
        ]);

        // Notify the technician
        $notificationService = app(NotificationService::class);
        $notificationService->notifyProposal(
            $request->technician_id,
            $clientRequest->title,
            $proposal->id
        );

        return response()->json([
            'message' => 'Technicien assigné manuellement',
            'data' => $proposal->load('technician'),
        ]);
    }

    /**
     * GET /v1/admin/map — Cartographie : techniciens référencés et demandes actives géolocalisés.
     */
    public function map()
    {
        $technicians = DB::select("
            SELECT u.id, u.name, u.verified, u.rating,
                   ST_Y(l.geom::geometry) AS latitude, ST_X(l.geom::geometry) AS longitude,
                   COALESCE(NULLIF(l.meta->>'radius', '')::float, 50) AS radius_km,
                   l.city,
                   COALESCE((SELECT json_agg(s.name) FROM technician_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.user_id = u.id), '[]'::json) AS skills
            FROM users u
            JOIN model_has_roles mr ON mr.model_id::text = u.id::text
            JOIN roles r ON r.id = mr.role_id AND r.name = 'technicien'
            JOIN locations l ON l.user_id = u.id AND l.is_default = true AND l.geom IS NOT NULL
            WHERE u.is_active = true
        ");

        $requests = DB::select("
            SELECT cr.id, cr.title, cr.status,
                   ST_Y(l.geom::geometry) AS latitude, ST_X(l.geom::geometry) AS longitude
            FROM client_requests cr
            JOIN locations l ON l.id = cr.address_id AND l.geom IS NOT NULL
            WHERE cr.status NOT IN ('completed', 'cancelled')
            ORDER BY cr.created_at DESC
            LIMIT 500
        ");

        return response()->json([
            'status' => 'success',
            'data' => [
                'technicians' => array_map(function ($t) {
                    $t->skills = json_decode($t->skills, true) ?: [];
                    return $t;
                }, $technicians),
                'requests' => $requests,
            ],
        ]);
    }
}
