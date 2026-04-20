<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Models\Like;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Toggle like/unlike sur un post.
     */
    public function toggle(Request $request, string $postId)
    {
        Post::findOrFail($postId);
        $userId = $request->user()->id;

        $existing = Like::where('post_id', $postId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            Like::create([
                'post_id' => $postId,
                'user_id' => $userId,
            ]);
            $liked = true;
        }

        $likesCount = Like::where('post_id', $postId)->count();

        return response()->json([
            'status'      => 'success',
            'liked'       => $liked,
            'likes_count' => $likesCount,
        ]);
    }

    /**
     * Liste des utilisateurs ayant like un post.
     */
    public function likers(Request $request, string $postId)
    {
        Post::findOrFail($postId);

        $likers = Like::where('post_id', $postId)
            ->with('user:id,name,avatar_url')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $likers->through(fn ($like) => [
                'user_id'    => $like->user_id,
                'name'       => $like->user->name ?? null,
                'avatar_url' => $like->user->avatar_url ?? null,
                'liked_at'   => $like->created_at?->toISOString(),
            ]),
        ]);
    }
}
