<?php

namespace App\Http\Middleware;

use App\Modules\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Exige la double authentification d'un client avant toute action financière, lorsque
 * `reliance.require_2fa_clients` est actif. Les autres rôles ne sont pas concernés.
 */
class RequireTwoFactorForClients
{
    public static function doitActiver(?User $user): bool
    {
        return $user !== null
            && config('reliance.require_2fa_clients')
            && $user->hasRole('client')
            && !$user->two_factor_enabled;
    }

    public function handle(Request $request, Closure $next)
    {
        if (self::doitActiver($request->user())) {
            return response()->json([
                'message' => 'Activez la double authentification (2FA) pour effectuer cette opération.',
                'requires_2fa_setup' => true,
            ], 403);
        }

        return $next($request);
    }
}
