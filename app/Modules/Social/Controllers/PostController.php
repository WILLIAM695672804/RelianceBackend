<?php

namespace App\Modules\Social\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\Post;
use App\Modules\Social\Requests\StorePostRequest;
use App\Modules\Social\Requests\UpdatePostRequest;
use App\Modules\Social\Resources\PostResource;
use App\Modules\Social\Services\FeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    public function __construct(
        private FeedService $feedService
    ) {}

    /**
     * Feed personnalise (posts des abonnements).
     */
    public function feed(Request $request)
    {
        $posts = $this->feedService->personalFeed(
            $request->user()->id,
            $request->integer('per_page', 15)
        );

        return PostResource::collection($posts);
    }

    /**
     * Feed global (explore).
     */
    public function explore(Request $request)
    {
        $posts = $this->feedService->globalFeed(
            $request->integer('per_page', 15)
        );

        return PostResource::collection($posts);
    }

    /**
     * Feed trending (populaire).
     */
    public function trending(Request $request)
    {
        $posts = $this->feedService->trendingFeed(
            $request->integer('per_page', 15)
        );

        return PostResource::collection($posts);
    }

    /**
     * Recherche full-text dans les posts.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $posts = $this->feedService->search(
            $request->q,
            $request->integer('per_page', 15)
        );

        return PostResource::collection($posts);
    }

    /**
     * Posts d'un utilisateur specifique.
     */
    public function userPosts(Request $request, string $userId)
    {
        $posts = $this->feedService->userPosts(
            $userId,
            $request->integer('per_page', 15)
        );

        return PostResource::collection($posts);
    }

    /**
     * Creer un post.
     */
    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();
        $attachments = null;

        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('posts/attachments', 'public');
                $attachments[] = [
                    'path' => $path,
                    'url'  => Storage::disk('public')->url($path),
                    'type' => $file->getMimeType(),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $post = Post::create([
            'author_id'   => $request->user()->id,
            'content'     => $validated['content'] ?? null,
            'attachments' => $attachments,
        ]);

        $post->load('author');
        $post->loadCount(['likes', 'comments']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Publication creee avec succes',
            'data'    => new PostResource($post),
        ], 201);
    }

    /**
     * Voir un post.
     */
    public function show(string $id)
    {
        $post = Post::with('author')
            ->withCount(['likes', 'comments'])
            ->findOrFail($id);

        return new PostResource($post);
    }

    /**
     * Modifier un post (auteur uniquement).
     */
    public function update(UpdatePostRequest $request, string $id)
    {
        $post = Post::findOrFail($id);

        if ($post->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $post->update($request->validated());
        $post->load('author');
        $post->loadCount(['likes', 'comments']);

        return new PostResource($post);
    }

    /**
     * Supprimer un post (auteur uniquement).
     */
    public function destroy(Request $request, string $id)
    {
        $post = Post::findOrFail($id);

        if ($post->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        // Supprimer les fichiers attaches
        if ($post->attachments) {
            foreach ($post->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }

        $post->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Publication supprimee',
        ]);
    }
}
