<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Schedule\Controllers\ScheduleController;
use App\Modules\Schedule\Controllers\TimeSlotController;
use App\Modules\Schedule\Controllers\ScheduleExceptionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('technician')->group(function () {
        Route::get('/calendar', [ScheduleController::class, 'getCalendarData']);
        
        Route::post('/time-slots', [TimeSlotController::class, 'store']);
        Route::delete('/time-slots/{id}', [TimeSlotController::class, 'destroy']);
        
        Route::post('/exceptions', [ScheduleExceptionController::class, 'store']);
        Route::delete('/exceptions/{id}', [ScheduleExceptionController::class, 'destroy']);
    });
});
