<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Profile\Controllers\SkillController;
use App\Modules\Profile\Controllers\TechnicianProfileController;
use App\Modules\Profile\Controllers\TechnicianSkillController;
use App\Modules\Profile\Controllers\AgencyController;
use App\Modules\Profile\Controllers\AgencyMemberController;

Route::get('/skills', [SkillController::class, 'index']);
Route::get('/technicians', [\App\Modules\Profile\Controllers\TechnicianSearchController::class, 'index']);
Route::get('/technicians/{id}/profile', [TechnicianProfileController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('technician')->group(function () {
        Route::get('/profile', [TechnicianProfileController::class, 'show']);
        Route::get('/stats', [TechnicianProfileController::class, 'stats']);
        Route::put('/profile', [TechnicianProfileController::class, 'updateProfile']);
        Route::put('/location', [TechnicianProfileController::class, 'updateLocation']);
        
        Route::get('/skills', [TechnicianSkillController::class, 'index']);
        Route::post('/skills', [TechnicianSkillController::class, 'store']);
        Route::delete('/skills/{id}', [TechnicianSkillController::class, 'destroy']);
    });

    // Client/General Location Management
    Route::prefix('locations')->group(function () {
        Route::get('/', [\App\Modules\Profile\Controllers\UserLocationController::class, 'index']);
        Route::post('/', [\App\Modules\Profile\Controllers\UserLocationController::class, 'store']);
        Route::delete('/{id}', [\App\Modules\Profile\Controllers\UserLocationController::class, 'destroy']);
        Route::patch('/{id}/default', [\App\Modules\Profile\Controllers\UserLocationController::class, 'setDefault']);
    });

    // ── Agencies ─────────────────────────────────────────────
    Route::get('agencies', [AgencyController::class, 'index']);
    Route::post('agencies', [AgencyController::class, 'store']);
    Route::get('agencies/{id}', [AgencyController::class, 'show']);
    Route::put('agencies/{id}', [AgencyController::class, 'update']);
    Route::get('agencies/{id}/members', [AgencyMemberController::class, 'index']);
    Route::post('agencies/{id}/members', [AgencyMemberController::class, 'store']);
    Route::delete('agencies/{id}/members/{userId}', [AgencyMemberController::class, 'destroy']);
    Route::get('my-agency', [AgencyController::class, 'myAgency']);
});
