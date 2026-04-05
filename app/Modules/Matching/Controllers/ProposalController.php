<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    /**
     * Client selects a technician from matching results → creates a proposal.
     */
    public function selectTechnician(Request $request, string $requestId)
    {
        $request->validate([
            'technician_id' => 'required|uuid|exists:users,id',
            'price'         => 'required|numeric|min:0',
            'message'       => 'nullable|string|max:1000',
        ]);

        $clientRequest = ClientRequest::where('client_id', auth()->id())
            ->findOrFail($requestId);

        $proposal = Proposal::create([
            'request_id'    => $clientRequest->id,
            'technician_id' => $request->technician_id,
            'price'         => $request->price,
            'message'       => $request->message,
            'status'        => 'pending',
            'expires_at'    => now()->addHours(24),
        ]);

        $clientRequest->status = 'quoted';
        $clientRequest->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Proposition envoyée au technicien',
            'data'    => $proposal->load('technician'),
        ], 201);
    }

    /**
     * Technician views their pending proposals.
     */
    public function technicianProposals(Request $request)
    {
        $proposals = Proposal::where('technician_id', $request->user()->id)
            ->with(['request.client', 'request.address', 'request.category'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['data' => $proposals]);
    }

    /**
     * Technician accepts a proposal.
     */
    public function accept(Request $request, string $proposalId)
    {
        $proposal = Proposal::where('technician_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($proposalId);

        if ($proposal->expires_at && $proposal->expires_at->isPast()) {
            return response()->json([
                'message' => 'Cette proposition a expiré.'
            ], 410);
        }

        $proposal->status = 'accepted';
        $proposal->save();

        $proposal->request->status = 'assigned';
        $proposal->request->save();

        // Reject other pending proposals for the same request
        Proposal::where('request_id', $proposal->request_id)
            ->where('id', '!=', $proposal->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Proposition acceptée',
            'data'    => $proposal->load(['request', 'technician']),
        ]);
    }

    /**
     * Technician refuses a proposal.
     */
    public function refuse(Request $request, string $proposalId)
    {
        $proposal = Proposal::where('technician_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($proposalId);

        $proposal->status = 'refused';
        $proposal->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Proposition refusée',
        ]);
    }
}
