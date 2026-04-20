<?php
// RelianceBackend/app/Modules/Notification/Routes/api.php

use Illuminate\Support\Facades\Route;
use App\Modules\Notification\Controllers\NotificationController;
use App\Modules\Notification\Controllers\PushTokenController;
use App\Modules\Notification\Controllers\NotificationSettingController;

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Notifications ───────────────────────────────────────
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);

    // ── Push Tokens ─────────────────────────────────────────
    Route::post('push-tokens', [PushTokenController::class, 'store']);
    Route::delete('push-tokens/{id}', [PushTokenController::class, 'destroy']);

    // ── Notification Settings ───────────────────────────────
    Route::get('notification-settings', [NotificationSettingController::class, 'index']);
    Route::put('notification-settings', [NotificationSettingController::class, 'update']);
});
