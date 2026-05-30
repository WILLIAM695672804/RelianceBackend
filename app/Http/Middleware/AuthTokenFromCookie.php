<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Reads the auth_token from an httpOnly cookie and injects it
 * as a Bearer Authorization header so Sanctum can authenticate normally.
 */
class AuthTokenFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        if (
            !$request->bearerToken() &&
            $request->cookie('auth_token')
        ) {
            $request->headers->set(
                'Authorization',
                'Bearer ' . $request->cookie('auth_token')
            );
        }

        return $next($request);
    }
}
