<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Models\Comment;
use App\Modules\Social\Requests\StoreCommentRequest;
use App\Modules\Social\Resources\CommentResource;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Lister les commentaires d'un post (pagine).
     */
    public function index(Request $request, string $postId)
    {
        Post::findOrFail($postId);

        $comments = Comment::where('post_id', $postId)
            ->with('author:id,name,avatar_url')
            ->orderBy('created_at', 'asc')
            ->paginate($request->integer('per_page', 20));

        return CommentResource::collection($comments);
    }

    /**
     * Ajouter un commentaire a un post.
     */
    public function store(StoreCommentRequest $request, string $postId)
    {
        Post::findOrFail($postId);

        $comment = Comment::create([
            'post_id'   => $postId,
            'author_id' => $request->user()->id,
            'content'   => $request->validated()['content'],
        ]);

        $comment->load('author:id,name,avatar_url');

        return response()->json([
            'status'  => 'success',
            'message' => 'Commentaire ajoute',
            'data'    => new CommentResource($comment),
        ], 201);
    }

    /**
     * Modifier un commentaire (auteur uniquement).
     */
    public function update(Request $request, string $postId, string $commentId)
    {
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::where('post_id', $postId)->findOrFail($commentId);

        if ($comment->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $comment->update(['content' => $request->content]);
        $comment->load('author:id,name,avatar_url');

        return new CommentResource($comment);
    }

    /**
     * Supprimer un commentaire (auteur ou auteur du post).
     */
    public function destroy(Request $request, string $postId, string $commentId)
    {
        $comment = Comment::where('post_id', $postId)->findOrFail($commentId);
        $post = Post::findOrFail($postId);
        $userId = $request->user()->id;

        if ($comment->author_id !== $userId && $post->author_id !== $userId) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $comment->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Commentaire supprime',
        ]);
    }
}
