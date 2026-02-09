<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @method \Illuminate\Database\Eloquent\Collection tokens()
 */

class AuthController extends Controller
{
    /**
     * Inscription
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role ?? 'client',
        ]);
        $token = $user->createToken('api-token')->plainTextToken;
        $user->assignRole($user->role);
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Inscription réussie',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Connexion
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Compte désactivé'
            ], 403);
        }
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user,
            'token'   => $token,
        ]);

        // Login échoué
        Log::warning('Login failed', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        // Login réussi
        Log::info('Login success', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout()
    {
        $user = auth()->user();

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
    /**
     * Utilisateur connecté
     */
    public function me()
    {
        return response()->json([
            'user' => auth()->user()
        ]);
    }
}
