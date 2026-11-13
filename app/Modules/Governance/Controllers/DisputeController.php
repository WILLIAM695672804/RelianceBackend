<?php

namespace App\Modules\Governance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Models\Dispute;
use App\Modules\Governance\Models\DisputeMessage;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use App\Modules\Finance\Services\EscrowService;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    /**
     * POST /v1/disputes — Open a dispute.
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_id' => 'required|uuid|exists:client_requests,id',
            'against' => 'required|uuid|exists:users,id',
            'reason' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        // Un litige oppose les deux parties de la mission : le client de la demande et le
        // technicien mandaté (proposition acceptée). Tout autre couple est refusé.
        $clientRequest = ClientRequest::findOrFail($request->request_id);
        $technicianId = Proposal::where('request_id', $clientRequest->id)->where('status', 'accepted')->value('technician_id');
        $parties = array_filter([$clientRequest->client_id, $technicianId]);
        $openerId = $request->user()->id;

        if (!in_array($openerId, $parties, true)
            || !in_array($request->against, $parties, true)
            || $request->against === $openerId) {
            return response()->json(['message' => 'Un litige ne peut opposer que les deux parties de la mission.'], 403);
        }

        $dispute = Dispute::create([
            'request_id' => $request->request_id,
            'opened_by' => $request->user()->id,
            'against' => $request->against,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'open',
        ]);

        event(new \App\Events\Admin\DisputeOpened($dispute));

        return response()->json([
            'message' => 'Litige ouvert',
            'data' => $dispute->load(['opener', 'defendant']),
        ], 201);
    }

    /**
     * GET /v1/disputes — List user's disputes.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $disputes = Dispute::where('opened_by', $userId)
            ->orWhere('against', $userId)
            ->with(['opener', 'defendant'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($disputes);
    }

    /**
     * GET /v1/disputes/{id} — Show dispute detail.
     */
    public function show(Request $request, string $id)
    {
        $userId = $request->user()->id;

        $dispute = Dispute::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('opened_by', $userId)->orWhere('against', $userId);
            })
            ->with(['opener', 'defendant', 'messages.sender'])
            ->firstOrFail();

        return response()->json(['data' => $dispute]);
    }

    /**
     * POST /v1/disputes/{id}/message — Add a message to a dispute.
     */
    public function addMessage(Request $request, string $id)
    {
        $request->validate([
            'message' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        $userId = $request->user()->id;

        $dispute = Dispute::where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('opened_by', $userId)->orWhere('against', $userId);
            })
            ->firstOrFail();

        if (in_array($dispute->status, ['resolved', 'closed'])) {
            return response()->json(['message' => 'Ce litige est déjà résolu'], 422);
        }

        $message = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'sender_id' => $userId,
            'message' => $request->message,
            'attachments' => $request->attachments,
        ]);

        event(new \App\Events\DisputeMessageCreated($message));

        return response()->json([
            'message' => 'Message ajouté',
            'data' => $message->load('sender'),
        ]);
    }

    // ── Admin endpoints ──────────────────────────────────────

    /**
     * GET /v1/admin/disputes — All disputes.
     */
    public function adminIndex(Request $request)
    {
        $disputes = Dispute::with(['opener', 'defendant', 'request'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($disputes);
    }

    /**
     * POST /v1/admin/disputes/{id}/resolve — Resolve a dispute.
     */
    public function resolve(Request $request, string $id)
    {
        $request->validate([
            'resolution' => 'required|string',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        $dispute = Dispute::findOrFail($id);

        $dispute->update([
            'status' => 'resolved',
            'resolution' => $request->resolution,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        event(new \App\Events\DisputeStatusChanged($dispute->fresh()));

        return response()->json([
            'message' => 'Litige résolu',
            'data' => $dispute->load(['opener', 'defendant', 'resolver']),
        ]);
    }
}
