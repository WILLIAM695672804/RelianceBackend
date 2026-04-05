<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Kyc\Controllers\KycController;

// Ce fichier est inclus dans le groupe 'auth:sanctum' de App/Modules/Auth/Routes/api.php
// Il n'est donc pas nécessaire de redéfinir le middleware ici pour les routes de base.

Route::get('/kyc/mine', [KycController::class, 'myDocuments']);
Route::post('/kyc/upload', [KycController::class, 'upload']);

Route::middleware('role:admin')->group(function () {
    Route::get('/kyc/admin/documents', [KycController::class, 'adminIndex']);
    Route::post('/kyc/{id}/validate', [KycController::class, 'validateDocument']);
});