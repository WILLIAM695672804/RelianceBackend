<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Social\Controllers\PostController;
use App\Modules\Social\Controllers\CommentController;
use App\Modules\Social\Controllers\LikeController;
use App\Modules\Social\Controllers\FollowController;
use App\Modules\Social\Controllers\ConversationController;
use App\Modules\Social\Controllers\MessageController;

// ── Routes publiques ────────────────────────────────────────────
Route::get('/posts/explore', [PostController::class, 'explore']);
Route::get('/posts/search', [PostController::class, 'search']);
Route::get('/posts/{id}', [PostController::class, 'show']);
Route::get('/posts/{postId}/comments', [CommentController::class, 'index']);
Route::get('/posts/{postId}/likers', [LikeController::class, 'likers']);
Route::get('/users/{userId}/posts', [PostController::class, 'userPosts']);
Route::get('/users/{userId}/followers', [FollowController::class, 'followers']);
Route::get('/users/{userId}/following', [FollowController::class, 'following']);
Route::get('/users/{userId}/follow-counts', [FollowController::class, 'counts']);

// ── Routes authentifiees ────────────────────────────────────────
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
});
