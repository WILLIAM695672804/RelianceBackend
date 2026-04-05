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

    // Password Reset
    Route::post('/forgot-password', [\App\Modules\Auth\Controllers\PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [\App\Modules\Auth\Controllers\PasswordResetController::class, 'resetPassword']);

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
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
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

            // User management
            Route::get('/admin/users', [\App\Modules\Auth\Controllers\AdminUserController::class, 'index']);
            Route::get('/admin/users/{id}', [\App\Modules\Auth\Controllers\AdminUserController::class, 'show']);
            Route::patch('/admin/users/{id}/toggle-active', [\App\Modules\Auth\Controllers\AdminUserController::class, 'toggleActive']);

            // Technician approval workflow
            Route::get('/admin/technicians/pending', [\App\Modules\Auth\Controllers\AdminUserController::class, 'pendingTechnicians']);
            Route::post('/admin/technicians/{id}/approve', [\App\Modules\Auth\Controllers\AdminUserController::class, 'approveTechnician']);
            Route::post('/admin/technicians/{id}/reject', [\App\Modules\Auth\Controllers\AdminUserController::class, 'rejectTechnician']);
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

        /*
        |--------------------------------------------------------------------------
        | KYC (Authenticated)
        |--------------------------------------------------------------------------
        */
        // This includes the routes for viewing/uploading your OWN documents
        require base_path('app/Modules/Kyc/Routes/api.php');
    });

    /*
    |--------------------------------------------------------------------------
    | Email verification callback (public)
    |--------------------------------------------------------------------------
    */
    Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Http\Request $request, $id, $hash) {
        $user = \App\Modules\Auth\Models\User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Lien de vérification invalide.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        return response()->json([
            'message' => 'Email vérifié avec succès'
        ]);
    })->name('verification.verify')->middleware('signed');

    Route::middleware(['auth:sanctum', 'kyc'])->group(function () {
        // routes sensibles dependantes du KYC
    });
    
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // routes sécurisées dependantes de l'activation
    });
});
