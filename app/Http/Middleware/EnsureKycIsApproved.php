<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || !$user->hasApprovedKyc()) {
            return response()->json([
                'message' => 'KYC non validé'
            ], 403);
        }

        return $next($request);
    }
}