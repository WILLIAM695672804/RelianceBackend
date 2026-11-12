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

    // Renvoi public du lien de vérification (compte non vérifié, donc sans session) — réponse neutre
    Route::post('/email/resend', function (\Illuminate\Http\Request $request) {
        $request->validate(['email' => 'required|email']);
        $user = \App\Modules\Auth\Models\User::where('email', $request->email)->first();
        if ($user && !$user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                \Log::warning('Renvoi du lien de vérification échoué', ['erreur' => $e->getMessage()]);
            }
        }
        return response()->json(['message' => 'Si le compte existe et n\'est pas vérifié, un nouveau lien a été envoyé.']);
    })->middleware('throttle:5,1');

    // Password Reset
    Route::post('/forgot-password', [\App\Modules\Auth\Controllers\PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [\App\Modules\Auth\Controllers\PasswordResetController::class, 'resetPassword']);

    /*
    |--------------------------------------------------------------------------
    | Authentifié
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // 2FA Routes (accessible sans email vérifié pour permettre la config)
        Route::prefix('2fa')->group(function () {
            Route::post('/generate', [TwoFactorController::class, 'generateSecret']);
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
        });

        // Routes nécessitant un email vérifié
        Route::middleware('verified')->group(function () {

            // Profile Routes
            Route::prefix('profile')->group(function () {
                Route::put('/', [ProfileController::class, 'update']);
                Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
                Route::put('/password', [ProfileController::class, 'changePassword']);
            });

            // Role Upgrade — Client → Technicien
            Route::post('/request-upgrade', [\App\Modules\Auth\Controllers\RoleUpgradeController::class, 'requestUpgrade']);

            /*
            |--------------------------------------------------------------------------
            | Client (techniciens also allowed — §4.1 "Un technicien peut également agir en tant que client")
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
                Route::get('/admin/stats', [\App\Modules\Auth\Controllers\AdminDashboardController::class, 'stats']);
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

                // Role Upgrade Admin
                Route::get('/admin/upgrade-requests', [\App\Modules\Auth\Controllers\RoleUpgradeController::class, 'adminIndex']);
                Route::post('/admin/upgrade-requests/{id}/approve', [\App\Modules\Auth\Controllers\RoleUpgradeController::class, 'approve']);
                Route::post('/admin/upgrade-requests/{id}/reject', [\App\Modules\Auth\Controllers\RoleUpgradeController::class, 'reject']);
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

            try {
                auth()->user()->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                \Log::warning('Renvoi du lien de vérification échoué', ['erreur' => $e->getMessage()]);

                return response()->json(['message' => 'Envoi momentanément impossible, réessayez dans un instant.'], 503);
            }

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
