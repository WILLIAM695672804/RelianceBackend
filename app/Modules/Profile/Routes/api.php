<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Profile\Controllers\SkillController;
use App\Modules\Profile\Controllers\TechnicianProfileController;
use App\Modules\Profile\Controllers\TechnicianSkillController;

Route::get('/skills', [SkillController::class, 'index']);
Route::get('/technicians/{id}/profile', [TechnicianProfileController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('technician')->group(function () {
        Route::get('/profile', [TechnicianProfileController::class, 'show']);
        Route::put('/profile', [TechnicianProfileController::class, 'updateProfile']);
        Route::put('/location', [TechnicianProfileController::class, 'updateLocation']);
        
        Route::get('/skills', [TechnicianSkillController::class, 'index']);
        Route::post('/skills', [TechnicianSkillController::class, 'store']);
        Route::delete('/skills/{id}', [TechnicianSkillController::class, 'destroy']);
    });
});
