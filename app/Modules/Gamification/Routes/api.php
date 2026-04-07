<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Gamification\Controllers\ReviewController;
use App\Modules\Gamification\Controllers\BadgeController;

// Public routes
Route::get('/reviews/technician/{id}', [ReviewController::class, 'technicianReviews']);
Route::get('/badges', [BadgeController::class, 'index']);
Route::get('/badges/user/{id}', [BadgeController::class, 'userBadges']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/mine', [ReviewController::class, 'myReviews']);
    Route::get('/reputation/{id}', [BadgeController::class, 'reputation']);
});
