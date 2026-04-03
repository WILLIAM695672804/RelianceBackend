<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\TwoFactorController;
use App\Modules\Auth\Controllers\ProfileController;
use App\Modules\Auth\Controllers\RoleManagementController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    /*
    |--------------------------------------------------------------------------
    | Authentifié
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Route protégée (email verification désactivée temporairement)
            Route::get('/me', [AuthController::class, 'me']);

        // 2FA Routes
        Route::prefix('2fa')->group(function () {
            Route::post('/generate', [TwoFactorController::class, 'generateSecret']);
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
        });

        // Profile Routes
        Route::prefix('profile')->group(function () {
            Route::put('/', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'changePassword']);
        });

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

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
            Route::post('/admin/users/{user}/roles', [RoleManagementController::class, 'assignRole']);
            Route::delete('/admin/users/{user}/roles/{role}', [RoleManagementController::class, 'revokeRole']);
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
