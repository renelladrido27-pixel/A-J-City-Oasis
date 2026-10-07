<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenants must confirm the emailed 6-digit code before using the portal or
 * booking. The website sends them to the code screen; the mobile API answers
 * 403 with code "email_unverified" so the app can show its own code screen.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isTenant() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Verify your email address to continue.', 'code' => 'email_unverified'], 403)
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
