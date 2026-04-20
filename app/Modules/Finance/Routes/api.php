<?php
// RelianceBackend/app/Modules/Finance/Routes/api.php

use Illuminate\Support\Facades\Route;
use App\Modules\Finance\Controllers\WalletController;
use App\Modules\Finance\Controllers\PaymentController;
use App\Modules\Finance\Controllers\HoldController;
use App\Modules\Finance\Controllers\SubscriptionController;
use App\Modules\Finance\Controllers\PlatformFeeController;
use App\Modules\Finance\Controllers\CreditController;
use App\Modules\Finance\Controllers\WebhookController;

// ── Public Webhook Routes (no auth, verified by signature) ──
Route::post('webhooks/momo', [WebhookController::class, 'momo']);
Route::post('webhooks/om', [WebhookController::class, 'orangeMoney']);

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Wallet ──────────────────────────────────────────────
    Route::get('wallet', [WalletController::class, 'show']);
    Route::post('wallet/recharge', [WalletController::class, 'recharge']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('wallet/withdraw', [WalletController::class, 'withdraw']);

    // ── Payments ────────────────────────────────────────────
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{id}', [PaymentController::class, 'show']);
    Route::post('payments/{id}/confirm', [PaymentController::class, 'confirm']);

    // ── Holds (Escrow) ──────────────────────────────────────
    Route::get('holds', [HoldController::class, 'index']);
    Route::post('holds', [HoldController::class, 'store']);
    Route::post('holds/{id}/capture', [HoldController::class, 'capture']);
    Route::post('holds/{id}/release', [HoldController::class, 'release']);

    // ── Subscriptions ───────────────────────────────────────
    Route::get('subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::get('subscriptions/mine', [SubscriptionController::class, 'mine']);
    Route::post('subscriptions/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('subscriptions/cancel', [SubscriptionController::class, 'cancel']);

    // ── Credits & Exchange Rates ────────────────────────────
    Route::get('credits/rates', [CreditController::class, 'rates']);
    Route::post('credits/purchase', [CreditController::class, 'purchase']);

    // ── Admin Finance Routes ────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        // Subscriptions Admin
        Route::get('admin/subscriptions', [SubscriptionController::class, 'adminIndex']);
        Route::get('admin/subscriptions/stats', [SubscriptionController::class, 'adminStats']);
        Route::put('admin/subscriptions/{id}', [SubscriptionController::class, 'adminUpdate']);
        Route::post('admin/subscriptions/plans', [SubscriptionController::class, 'createPlan']);
        Route::put('admin/subscriptions/plans/{id}', [SubscriptionController::class, 'updatePlan']);

        // Platform Fees Admin
        Route::get('admin/fees', [PlatformFeeController::class, 'index']);
        Route::post('admin/fees', [PlatformFeeController::class, 'store']);
        Route::put('admin/fees/{id}', [PlatformFeeController::class, 'update']);
        Route::patch('admin/fees/{id}/toggle', [PlatformFeeController::class, 'toggle']);

        // Exchange Rates Admin
        Route::get('admin/credits/rates', [CreditController::class, 'adminRates']);
        Route::post('admin/credits/rates', [CreditController::class, 'setRate']);
    });
});
