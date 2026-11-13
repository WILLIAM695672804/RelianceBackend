<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Governance\Controllers\SlaRuleController;
use App\Modules\Governance\Controllers\SlaEventController;
use App\Modules\Governance\Controllers\AuditLogController;
use App\Modules\Governance\Controllers\AnalyticsController;
use App\Modules\Governance\Controllers\DisputeController;

Route::middleware('auth:sanctum')->group(function () {

    // ── SLA Events (authenticated users) ───────────────────
    Route::get('/sla-events/mine', [SlaEventController::class, 'mine']);
    Route::get('/sla-events/request/{requestId}', [SlaEventController::class, 'index']);

    // ── Disputes (authenticated users) ─────────────────────
    Route::post('/disputes', [DisputeController::class, 'store']);
    Route::get('/disputes', [DisputeController::class, 'index']);
    Route::get('/disputes/{id}', [DisputeController::class, 'show']);
    Route::post('/disputes/{id}/message', [DisputeController::class, 'addMessage']);

    // ── Admin-only routes ──────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        // Création d'un événement SLA (déclenche des pénalités de réputation) : administrateur uniquement.
        Route::post('/sla-events', [SlaEventController::class, 'store']);


        // SLA Rules
        Route::prefix('sla-rules')->group(function () {
            Route::get('/', [SlaRuleController::class, 'index']);
            Route::post('/', [SlaRuleController::class, 'store']);
            Route::get('/{id}', [SlaRuleController::class, 'show']);
            Route::put('/{id}', [SlaRuleController::class, 'update']);
            Route::patch('/{id}/toggle', [SlaRuleController::class, 'toggle']);
        });

        // Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/{id}', [AuditLogController::class, 'show']);
        });

        // Analytics
        Route::prefix('analytics')->group(function () {
            Route::get('/', [AnalyticsController::class, 'index']);
            Route::get('/summary', [AnalyticsController::class, 'summary']);
        });

        // Admin Disputes
        Route::get('/admin/disputes', [DisputeController::class, 'adminIndex']);
        Route::post('/admin/disputes/{id}/resolve', [DisputeController::class, 'resolve']);
    });

    // Analytics track (authenticated, non-admin)
    Route::post('/analytics/track', [AnalyticsController::class, 'track']);
});
