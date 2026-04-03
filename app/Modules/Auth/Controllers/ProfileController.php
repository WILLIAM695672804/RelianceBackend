<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Mettre à jour les informations du profil
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:50',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
        }

        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $user
        ]);
    }

    /**
     * Mettre à jour le mot de passe
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'L\'ancien mot de passe est incorrect.'
            ], 400);
        }

        $user->forceFill([
            'password' => Hash::make($request->new_password)
        ])->save();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.'
        ]);
    }

    /**
     * Mettre à jour l'avatar de l'utilisateur
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe et n'est pas par défaut
            if ($user->avatar_url && !str_contains($user->avatar_url, 'default')) {
                // TODO: Disk cleanup logic if needed
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = config('app.url') . '/storage/' . $path;
            $user->save();

            return response()->json([
                'message' => 'Avatar mis à jour',
                'avatar_url' => $user->avatar_url
            ]);
        }

        return response()->json(['message' => 'Aucun fichier reçu'], 400);
    }
}
