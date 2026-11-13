<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Matching\Controllers\ClientRequestController;
use App\Modules\Matching\Controllers\ProposalController;
use App\Modules\Matching\Controllers\SseNotificationController;
use App\Modules\Matching\Controllers\PositionController;
use App\Modules\Matching\Controllers\AdminMatchingController;

// SSE stream — auth handled manually (EventSource can't send headers)
Route::get('/proposals/stream', [SseNotificationController::class, 'stream']);

Route::middleware('auth:sanctum')->group(function () {

    // Client request management
    Route::prefix('requests')->group(function () {
        Route::post('/', [ClientRequestController::class, 'store']);
        Route::get('/', [ClientRequestController::class, 'index']);
        Route::get('/{id}', [ClientRequestController::class, 'show']);
        Route::post('/{id}/match', [ClientRequestController::class, 'match']);
        Route::post('/{id}/select-technician', [ProposalController::class, 'selectTechnician']);
    });

    // Technician proposal management
    Route::prefix('proposals')->group(function () {
        Route::get('/my', [ProposalController::class, 'technicianProposals']);
        Route::get('/check-new', [ProposalController::class, 'checkNotification']);
        Route::get('/active-mission', [ProposalController::class, 'activeMission']);
        Route::post('/{id}/accept', [ProposalController::class, 'accept']);
        Route::post('/{id}/refuse', [ProposalController::class, 'refuse']);
    });

    // Client active mission tracking
    Route::get('/mission/active', [ProposalController::class, 'clientActiveMission']);

    // Live position tracking
    Route::post('/position/update', [PositionController::class, 'update']);
    Route::get('/position/technician', [PositionController::class, 'getTechnicianPosition']);

    // ── Admin Matching Routes ────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/map', [AdminMatchingController::class, 'map']);
        Route::get('/admin/requests', [AdminMatchingController::class, 'index']);
        Route::post('/admin/requests/{id}/assign', [AdminMatchingController::class, 'assign']);
        Route::get('/admin/ml-models', [\App\Modules\Matching\Controllers\AdminMlController::class, 'index']);
        Route::post('/admin/ml-models/train', [\App\Modules\Matching\Controllers\AdminMlController::class, 'train']);
    });
});
