<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // Réponse identique que le compte existe ou non (ou que l'envoi soit temporairement limité) :
        // on ne doit pas permettre d'énumérer les comptes.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation vient d\'être envoyé.'
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès.'
            ]);
        }

        return response()->json([
            'message' => 'Token invalide ou expiré.'
        ], 400);
    }
}
