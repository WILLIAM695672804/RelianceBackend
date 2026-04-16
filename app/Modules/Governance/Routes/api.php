<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Governance\Controllers\SlaRuleController;
use App\Modules\Governance\Controllers\SlaEventController;
use App\Modules\Governance\Controllers\AuditLogController;
use App\Modules\Governance\Controllers\AnalyticsController;

Route::middleware('auth:sanctum')->group(function () {

    // ── SLA Events (authenticated users) ───────────────────
    Route::post('/sla-events', [SlaEventController::class, 'store']);
    Route::get('/sla-events/request/{requestId}', [SlaEventController::class, 'index']);

    // ── Admin-only routes ──────────────────────────────────
    Route::middleware('role:admin')->group(function () {

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
    });

    // Analytics track (authenticated, non-admin)
    Route::post('/analytics/track', [AnalyticsController::class, 'track']);
});
