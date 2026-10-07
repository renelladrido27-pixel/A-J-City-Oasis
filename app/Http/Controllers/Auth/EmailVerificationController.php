<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves both the website (redirects + session messages) and the mobile API
 * (JSON) — same rules, same service.
 */
class EmailVerificationController extends Controller
{
    public function __construct(protected EmailVerificationService $verification) {}

    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('tenant.dashboard');
        }

        return view('auth.verify-email', ['email' => $request->user()->email]);
    }

    public function verify(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Enter the 6-digit code from the email.']);

        $result = $this->verification->verify($request->user(), $request->input('code'));

        if ($result !== 'verified') {
            $message = $result === 'expired'
                ? 'That code has expired. Request a new one.'
                : 'That code is not correct. Check the email and try again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422)
                : back()->withErrors(['code' => $message]);
        }

        return $request->expectsJson()
            ? response()->json(['verified' => true])
            : redirect()->intended(route('tenant.dashboard'))->with('status', 'Email verified. Welcome to A & J OASIS!');
    }

    public function resend(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $this->verification->sendCode($request->user());
        }

        return $request->expectsJson()
            ? response()->json(['sent' => true])
            : back()->with('status', 'A new code was sent to '.$request->user()->email.'.');
    }
}
