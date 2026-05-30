<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        // Broadcast real-time notification to technician
        event(new \App\Events\ProposalCreated($proposal));

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

        event(new \App\Events\ProposalAccepted($proposal));

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

        event(new \App\Events\ProposalRefused($proposal));

        return response()->json([
            'status'  => 'success',
            'message' => 'Proposition refusée',
        ]);
    }

    /**
     * Lightweight notification check — returns cached notification if any.
     * Used as fallback when SSE is not available (e.g. php artisan serve).
     */
    public function checkNotification(Request $request)
    {
        $cacheKey = "sse_proposals_{$request->user()->id}";
        $notification = Cache::get($cacheKey);

        if ($notification) {
            Cache::forget($cacheKey);
            return response()->json(['has_new' => true, 'data' => $notification]);
        }

        return response()->json(['has_new' => false]);
    }

    /**
     * Get the active mission for the technician (accepted proposal with request details).
     */
    public function activeMission(Request $request)
    {
        $proposal = Proposal::where('technician_id', $request->user()->id)
            ->where('status', 'accepted')
            ->with(['request.client', 'request.address', 'request.category'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$proposal) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'proposal_id'  => $proposal->id,
                'price'        => $proposal->price,
                'accepted_at'  => $proposal->updated_at,
                'request'      => [
                    'id'          => $proposal->request->id,
                    'title'       => $proposal->request->title,
                    'description' => $proposal->request->description,
                    'status'      => $proposal->request->status,
                    'photos'      => $proposal->request->photos,
                    'category'    => $proposal->request->category?->name,
                ],
                'client' => [
                    'id'     => $proposal->request->client->id,
                    'name'   => $proposal->request->client->name,
                    'phone'  => $proposal->request->client->phone,
                    'avatar' => $proposal->request->client->avatar_url,
                ],
                'address' => $proposal->request->address ? [
                    'label'     => $proposal->request->address->label,
                    'city'      => $proposal->request->address->city,
                    'address'   => $proposal->request->address->address,
                    'latitude'  => $proposal->request->address->meta['latitude'] ?? null,
                    'longitude' => $proposal->request->address->meta['longitude'] ?? null,
                ] : null,
            ],
        ]);
    }

    /**
     * Client views the latest active request with assigned technician info.
     */
    public function clientActiveMission(Request $request)
    {
        $clientRequest = ClientRequest::where('client_id', $request->user()->id)
            ->whereIn('status', ['quoted', 'assigned'])
            ->with(['proposals' => function ($q) {
                $q->whereIn('status', ['pending', 'accepted'])
                  ->with('technician')
                  ->orderBy('created_at', 'desc');
            }, 'address', 'category'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$clientRequest) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        $activeProposal = $clientRequest->proposals->first();

        $activeJob = \App\Modules\Operations\Models\TechnicianJob::where('request_id', $clientRequest->id)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'job_id' => $activeJob?->id,
                'request' => [
                    'id'          => $clientRequest->id,
                    'title'       => $clientRequest->title,
                    'description' => $clientRequest->description,
                    'status'      => $clientRequest->status,
                    'category'    => $clientRequest->category?->name,
                    'created_at'  => $clientRequest->created_at,
                ],
                'address' => $clientRequest->address ? [
                    'label'     => $clientRequest->address->label,
                    'city'      => $clientRequest->address->city,
                    'address'   => $clientRequest->address->address,
                    'latitude'  => $clientRequest->address->meta['latitude'] ?? null,
                    'longitude' => $clientRequest->address->meta['longitude'] ?? null,
                ] : null,
                'proposal' => $activeProposal ? [
                    'id'     => $activeProposal->id,
                    'price'  => $activeProposal->price,
                    'status' => $activeProposal->status,
                ] : null,
                'technician' => $activeProposal?->technician ? [
                    'id'          => $activeProposal->technician->id,
                    'name'        => $activeProposal->technician->name,
                    'phone'       => $activeProposal->technician->phone,
                    'avatar'      => $activeProposal->technician->avatar_url,
                    'rating'      => $activeProposal->technician->rating,
                    'num_reviews' => $activeProposal->technician->num_reviews,
                ] : null,
            ],
        ]);
    }
}
