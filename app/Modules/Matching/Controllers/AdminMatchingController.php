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
}
