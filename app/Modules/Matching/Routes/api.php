<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Matching\Controllers\ClientRequestController;
use App\Modules\Matching\Controllers\ProposalController;
use App\Modules\Matching\Controllers\SseNotificationController;
use App\Modules\Matching\Controllers\PositionController;

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
});
