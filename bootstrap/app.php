<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'email.verified' => EnsureEmailIsVerified::class,
        ]);

        // Logging out must always work, even from a page left open so long
        // that its form token expired (that used to end in "419 Page Expired").
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/xendit',
            'logout',
        ]);

        // Trust the demo tunnel's (Cloudflare/ngrok) forwarded headers so url()/asset()
        // generate https:// links even though the app itself is served over plain HTTP.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Any other form sent from a page that sat open past the session
        // lifetime: go back with a plain-language message instead of the bare
        // "419 Page Expired" screen. (A now-signed-out user lands on the login page.)
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->withErrors(['session' => 'That page was open for too long and expired. Please try again.']);
        });
    })->create();
