<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Kyc\Controllers\KycController;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    Route::post('/kyc/upload', [KycController::class, 'upload']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/kyc/{id}/validate', [KycController::class, 'validateDocument']);
    });

});