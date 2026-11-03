<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating an account takes effect at once: someone already signed in
 * (a website session or the app's token) is signed out on their next request,
 * not only blocked the next time they log in.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'This account has been deactivated. Please contact the A&J CITY OASIS office.';

    public function handle(Request $request, Closure $next, ?string $guard = null): Response
    {
        $user = Auth::guard($guard)->user();

        // Only an account switched off by the admin (is_active = false).
        if (! $user || $user->is_active !== false) {
            return $next($request);
        }

        // App: revoke the token it used, and tell it why.
        if ($guard === 'sanctum') {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => self::MESSAGE, 'code' => 'account_deactivated'], 403);
        }

        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE, 'code' => 'account_deactivated'], 403);
        }

        return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }
}
