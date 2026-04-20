<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Follow;
use App\Modules\Social\Resources\FollowResource;
use App\Modules\Social\Services\FeedService;
use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(
        private FeedService $feedService
    ) {}

    /**
     * Suivre un utilisateur.
     */
    public function follow(Request $request, string $userId)
    {
        User::findOrFail($userId);
        $followerId = $request->user()->id;

        if ($followerId === $userId) {
            return response()->json(['message' => 'Vous ne pouvez pas vous suivre vous-meme'], 422);
        }

        $existing = Follow::where('follower_id', $followerId)
            ->where('followee_id', $userId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous suivez deja cet utilisateur'], 409);
        }

        Follow::create([
            'follower_id' => $followerId,
            'followee_id' => $userId,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur suivi avec succes',
            'following' => true,
            'followers_count' => Follow::where('followee_id', $userId)->count(),
        ], 201);
    }

    /**
     * Ne plus suivre un utilisateur.
     */
    public function unfollow(Request $request, string $userId)
    {
        $deleted = Follow::where('follower_id', $request->user()->id)
            ->where('followee_id', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Vous ne suivez pas cet utilisateur'], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Desabonne avec succes',
            'following' => false,
            'followers_count' => Follow::where('followee_id', $userId)->count(),
        ]);
    }

    /**
     * Verifier si on suit un utilisateur.
     */
    public function check(Request $request, string $userId)
    {
        $isFollowing = Follow::where('follower_id', $request->user()->id)
            ->where('followee_id', $userId)
            ->exists();

        return response()->json(['following' => $isFollowing]);
    }

    /**
     * Liste des followers d'un utilisateur.
     */
    public function followers(Request $request, string $userId)
    {
        $followers = Follow::where('followee_id', $userId)
            ->with('follower:id,name,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return FollowResource::collection($followers);
    }

    /**
     * Liste des utilisateurs suivis.
     */
    public function following(Request $request, string $userId)
    {
        $following = Follow::where('follower_id', $userId)
            ->with('followee:id,name,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return FollowResource::collection($following);
    }

    /**
     * Compteurs followers / following d'un utilisateur.
     */
    public function counts(string $userId)
    {
        return response()->json([
            'followers_count' => Follow::where('followee_id', $userId)->count(),
            'following_count' => Follow::where('follower_id', $userId)->count(),
        ]);
    }

    /**
     * Suggestions de personnes a suivre.
     */
    public function suggestions(Request $request)
    {
        $suggestions = $this->feedService->suggestionsToFollow(
            $request->user()->id,
            $request->integer('limit', 10)
        );

        return response()->json(['data' => $suggestions]);
    }
}
