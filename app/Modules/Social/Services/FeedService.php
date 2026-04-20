<?php

namespace App\Modules\Social\Services;

use App\Modules\Social\Models\Post;
use App\Modules\Social\Models\Follow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FeedService
{
    /**
     * Feed personnalise : posts des utilisateurs suivis + ses propres posts.
     */
    public function personalFeed(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        $followedIds = Follow::where('follower_id', $userId)
            ->pluck('followee_id')
            ->push($userId);

        return Post::whereIn('author_id', $followedIds)
            ->with('author')
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Feed global (explore) : tous les posts, les plus recents.
     */
    public function globalFeed(int $perPage = 15): LengthAwarePaginator
    {
        return Post::with('author')
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Feed trending : posts les plus likes sur les 7 derniers jours.
     */
    public function trendingFeed(int $perPage = 15): LengthAwarePaginator
    {
        return Post::with('author')
            ->withCount(['likes', 'comments'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('likes_count', 'desc')
            ->paginate($perPage);
    }

    /**
     * Recherche full-text dans les posts (index GIN français).
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        return Post::with('author')
            ->withCount(['likes', 'comments'])
            ->whereRaw("to_tsvector('french', coalesce(content, '')) @@ plainto_tsquery('french', ?)", [$query])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Posts d'un utilisateur specifique (profil).
     */
    public function userPosts(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Post::where('author_id', $userId)
            ->with('author')
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Suggestions de personnes a suivre.
     * Exclut les utilisateurs deja suivis et soi-meme.
     * Priorite : techniciens verifies avec le meilleur rating.
     */
    public function suggestionsToFollow(string $userId, int $limit = 10): array
    {
        $alreadyFollowing = Follow::where('follower_id', $userId)
            ->pluck('followee_id')
            ->push($userId);

        return DB::table('users')
            ->whereNotIn('id', $alreadyFollowing)
            ->orderByDesc('rating')
            ->limit($limit)
            ->select('id', 'name', 'avatar_url', 'rating', 'is_verified')
            ->get()
            ->toArray();
    }
}
