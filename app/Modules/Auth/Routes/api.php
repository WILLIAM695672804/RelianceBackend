<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    /*
    |--------------------------------------------------------------------------
    | Authentifié
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Email vérifié
        Route::middleware('verified')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
        });

        Route::post('/logout', [AuthController::class, 'logout']);

        /*
        |--------------------------------------------------------------------------
        | Client
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:create_request')->post('/requests', function () {
            return 'Création demande OK';
        });

        /*
        |--------------------------------------------------------------------------
        | Agence
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:agence')->group(function () {
            Route::get('/agency/dashboard', fn() => 'Agence OK');
        });

        Route::middleware('permission:manage_technicians')->post('/agency/technicians', function () {
            return 'Gestion techniciens OK';
        });

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:admin')->group(function () {
            Route::get('/admin/dashboard', function () {
                return 'Admin OK';
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Email verification
        |--------------------------------------------------------------------------
        */
        Route::post('/email/verification-notification', function () {
            if (auth()->user()->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Email déjà vérifié'
                ]);
            }

            auth()->user()->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Email de vérification renvoyé'
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Email verification callback (public)
    |--------------------------------------------------------------------------
    */
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return response()->json([
            'message' => 'Email vérifié avec succès'
        ]);
    })->name('verification.verify');

    /*
    |--------------------------------------------------------------------------
    | KYC
    |--------------------------------------------------------------------------
    */
    require base_path('app/Modules/Kyc/Routes/api.php');

    Route::middleware(['auth:sanctum', 'kyc'])->group(function () {
        // routes sensibles
    });
    
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // routes sécurisées
    });
});
