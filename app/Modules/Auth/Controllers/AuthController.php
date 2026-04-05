<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

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
        $roleName = $request->role ?? 'client';
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole($roleName);
        
        // Techniciens require admin approval before being listed
        if ($roleName === 'technicien') {
            $user->verified = false;
            $user->save();
        }

        // Meta Data Builder
        $meta = [];

        // Informations métier additionnelles stockées directement en metadata JSON
        if ($roleName === 'technicien') {
            $meta['bio'] = $request->bio;
            $meta['competences_intro'] = $request->competences;
        } elseif ($roleName === 'agence') {
            $meta['agency_name'] = $request->agency_name;
            $meta['address'] = $request->address;
            $meta['description'] = $request->description;
        }

        if(!empty($meta)) {
            $user->meta = $meta;
            $user->save();
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $user->sendEmailVerificationNotification();

        // Charger le rôle
        $user->load('roles');

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
            Log::warning('Login failed', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Identifiants invalides'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Compte désactivé'
            ], 403);
        }

        if ($user->two_factor_enabled) {
            if (!$request->code) {
                return response()->json([
                    'message' => 'Code 2FA requis',
                    'requires_2fa' => true
                ], 403);
            }

            $google2fa = new Google2FA();
            $secret = decrypt($user->two_factor_secret);
            
            if (!$google2fa->verifyKey($secret, $request->code)) {
                Log::warning('Login failed (2FA)', [
                    'email' => $request->email,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'message' => 'Code 2FA invalide'
                ], 401);
            }
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('Login success', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        // Charger les relations
        $user->load('roles');

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user,
            'token'   => $token,
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
     * Rafraîchir le token
     */
    public function refresh()
    {
        $user = auth()->user();
        
        // Supprimer le token actuel
        $user->currentAccessToken()->delete();
        
        // Créer un nouveau token
        $token = $user->createToken('api-token')->plainTextToken;
        
        $user->load('roles');

        return response()->json([
            'message' => 'Token rafraîchi',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Utilisateur connecté
     */
    public function me()
    {
        $user = auth()->user();
        $user->load('roles');

        return response()->json([
            'user' => $user
        ]);
    }
}
