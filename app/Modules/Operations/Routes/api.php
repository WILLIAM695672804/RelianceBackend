<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Operations\Controllers\QuoteController;
use App\Modules\Operations\Controllers\InvoiceController;
use App\Modules\Operations\Controllers\AppointmentController;
use App\Modules\Operations\Controllers\JobController;
use App\Modules\Operations\Controllers\InterventionController;

Route::middleware('auth:sanctum')->group(function () {

    // ── Devis (Quotes) ─────────────────────────────────────
    Route::prefix('quotes')->group(function () {
        Route::post('/', [QuoteController::class, 'store']);
        Route::get('/{id}', [QuoteController::class, 'show']);
        Route::post('/{id}/send', [QuoteController::class, 'send']);
        Route::post('/{id}/accept', [QuoteController::class, 'accept']);
        Route::post('/{id}/reject', [QuoteController::class, 'reject']);
    });

    // Devis par demande client
    Route::get('/requests/{requestId}/quotes', [QuoteController::class, 'index']);

    // ── Factures (Invoices) ─────────────────────────────────
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::post('/{id}/pay', [InvoiceController::class, 'markPaid']);
    });

    // ── Rendez-vous (Appointments) ──────────────────────────
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::get('/upcoming', [AppointmentController::class, 'upcoming']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{id}', [AppointmentController::class, 'show']);
        Route::patch('/{id}/status', [AppointmentController::class, 'updateStatus']);
    });

    // ── Jobs (Suivi d'intervention) ��────────────────────────
    Route::prefix('jobs')->group(function () {
        Route::get('/', [JobController::class, 'index']);
        Route::post('/', [JobController::class, 'store']);
        Route::get('/{id}', [JobController::class, 'show']);
        Route::patch('/{id}/status', [JobController::class, 'updateStatus']);
        Route::put('/{id}/phases', [JobController::class, 'updatePhases']);
        Route::get('/{id}/tracking', [JobController::class, 'tracking']);
    });

    // ── Interventions ───────���──────────────────────────────
    Route::prefix('interventions')->group(function () {
        Route::get('/', [InterventionController::class, 'index']);
        Route::post('/', [InterventionController::class, 'store']);
        Route::get('/{id}', [InterventionController::class, 'show']);
        Route::post('/{id}/start', [InterventionController::class, 'start']);
        Route::post('/{id}/complete', [InterventionController::class, 'complete']);
    });
});
