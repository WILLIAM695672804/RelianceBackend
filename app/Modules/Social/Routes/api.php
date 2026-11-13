<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Social\Controllers\PostController;
use App\Modules\Social\Controllers\CommentController;
use App\Modules\Social\Controllers\LikeController;
use App\Modules\Social\Controllers\FollowController;
use App\Modules\Social\Controllers\ConversationController;
use App\Modules\Social\Controllers\MessageController;
use App\Modules\Social\Controllers\AdminConversationController;

// ── Routes publiques ────────────────────────────────────────────
Route::get('/posts/explore', [PostController::class, 'explore']);
Route::get('/posts/search', [PostController::class, 'search']);
// Contrainte UUID indispensable : sans elle, `/posts/feed` et `/posts/trending`
// (déclarées plus bas, derrière l'authentification) sont capturées par `/posts/{id}`.
Route::get('/posts/{id}', [PostController::class, 'show'])->whereUuid('id');
Route::get('/posts/{postId}/comments', [CommentController::class, 'index'])->whereUuid('postId');
Route::get('/posts/{postId}/likers', [LikeController::class, 'likers'])->whereUuid('postId');
Route::get('/users/{userId}/posts', [PostController::class, 'userPosts']);
Route::get('/users/{userId}/followers', [FollowController::class, 'followers']);
Route::get('/users/{userId}/following', [FollowController::class, 'following']);
Route::get('/users/{userId}/follow-counts', [FollowController::class, 'counts']);

// ── Routes authentifiees ────────────────────────────────────────
// Pièces jointes de messagerie : servies depuis le disque privé sur URL temporaire signée.
Route::get('/attachments/{id}/fichier', [MessageController::class, 'file'])
    ->name('messagerie.piece-jointe')->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {

    // ── Posts ───────────────────────────────────────────────────
    Route::prefix('posts')->group(function () {
        Route::get('/feed', [PostController::class, 'feed']);
        Route::get('/trending', [PostController::class, 'trending']);
        Route::post('/', [PostController::class, 'store']);
        Route::put('/{id}', [PostController::class, 'update']);
        Route::delete('/{id}', [PostController::class, 'destroy']);
    });

    // ── Commentaires ───────────────────────────────────────────
    Route::prefix('posts/{postId}/comments')->group(function () {
        Route::post('/', [CommentController::class, 'store']);
        Route::put('/{commentId}', [CommentController::class, 'update']);
        Route::delete('/{commentId}', [CommentController::class, 'destroy']);
    });

    // ── Likes ──────────────────────────────────────────────────
    Route::post('/posts/{postId}/like', [LikeController::class, 'toggle']);

    // ── Follows ────────────────────────────────────────────────
    Route::prefix('follows')->group(function () {
        Route::post('/{userId}', [FollowController::class, 'follow']);
        Route::delete('/{userId}', [FollowController::class, 'unfollow']);
        Route::get('/{userId}/check', [FollowController::class, 'check']);
        Route::get('/suggestions', [FollowController::class, 'suggestions']);
    });

    // ── Conversations ──────────────────────────────────────────
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'store']);
        Route::get('/unread-count', [ConversationController::class, 'unreadCount']);
        Route::get('/{id}', [ConversationController::class, 'show']);
    });

    // ── Messages ───────────────────────────────────────────────
    Route::prefix('conversations/{conversationId}/messages')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::post('/', [MessageController::class, 'store']);
        Route::post('/read', [MessageController::class, 'markAsRead']);
        Route::get('/attachments', [MessageController::class, 'attachments']);
        Route::delete('/{messageId}', [MessageController::class, 'destroy']);
    });

    // ── Admin Conversation Supervision ─────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/conversations', [AdminConversationController::class, 'index']);
        Route::get('/admin/conversations/search', [AdminConversationController::class, 'search']);
        Route::get('/admin/conversations/{id}', [AdminConversationController::class, 'show']);
    });
});
