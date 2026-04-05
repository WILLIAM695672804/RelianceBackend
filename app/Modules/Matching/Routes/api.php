<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Matching\Controllers\ClientRequestController;
use App\Modules\Matching\Controllers\ProposalController;

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
        Route::post('/{id}/accept', [ProposalController::class, 'accept']);
        Route::post('/{id}/refuse', [ProposalController::class, 'refuse']);
    });
});
