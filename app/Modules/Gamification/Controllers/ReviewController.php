<?php
namespace App\Modules\Gamification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Gamification\Models\Review;
use App\Modules\Gamification\Services\ReputationService;
use App\Modules\Gamification\Services\BadgeAwardService;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, ReputationService $reputationService, BadgeAwardService $badgeAwardService)
    {
        $request->validate([
            'request_id' => 'required|uuid|exists:client_requests,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string|max:2000',
        ]);

        $clientRequest = ClientRequest::where('id', $request->request_id)
            ->where('client_id', auth()->id())
            ->where('status', 'completed')
            ->firstOrFail();

        $proposal = Proposal::where('request_id', $clientRequest->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        $existing = Review::where('reviewer_id', auth()->id())
            ->where('request_id', $clientRequest->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous avez deja laisse un avis pour cette mission.'], 409);
        }

        $review = Review::create([
            'reviewer_id' => auth()->id(),
            'target_id'   => $proposal->technician_id,
            'request_id'  => $clientRequest->id,
            'rating'      => $request->rating,
            'comment'     => $request->comment,
        ]);

        $reputationService->recalculateAfterReview($review);
        $newBadges = $badgeAwardService->evaluate($proposal->technician);

        return response()->json([
            'status'     => 'success',
            'message'    => 'Avis enregistre avec succes',
            'data'       => $review->load('reviewer'),
            'new_badges' => $newBadges,
        ], 201);
    }

    public function technicianReviews(string $technicianId)
    {
        $reviews = Review::where('target_id', $technicianId)
            ->with('reviewer:id,name,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['data' => $reviews]);
    }

    public function myReviews(Request $request)
    {
        $reviews = Review::where('target_id', $request->user()->id)
            ->with(['reviewer:id,name,avatar_url', 'request:id,title'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json(['data' => $reviews]);
    }
}
