<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The site uses Bootstrap 5, not Tailwind. Laravel's default (Tailwind)
        // pagination renders unstyled here, with a giant "<" arrow on page 2+.
        Paginator::useBootstrapFive();

        // Brute-force protection once the app is on the public internet: a few
        // password guesses per account per IP, and a cap on account creation
        // (the mobile "create account & book" endpoint also makes accounts).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('signup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Entering / re-sending the emailed verification code.
        RateLimiter::for('verify-email', fn (Request $request) => Limit::perMinute(6)
            ->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));

        // One password policy for every form that uses Password::defaults():
        // at least 8 characters with upper- and lower-case letters, a number
        // and a symbol.
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());
    }
}
