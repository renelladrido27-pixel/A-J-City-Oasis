<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::call + Artisan::call, NOT Schedule::command: the latter launches
// each command as a separate process with proc_open(), which shared hosts
// (Hostinger) disable — the jobs would silently never run. Running them
// inside the scheduler's own process needs nothing special.
Schedule::call(fn () => Artisan::call('app:expire-stale-bookings'))
    ->daily()
    ->name('expire-stale-bookings');

Schedule::call(fn () => Artisan::call('app:check-overdue-payments'))
    ->daily()
    ->name('check-overdue-payments');

// Next month's rent bill for every active lease, so tenants always see
// their next payment without the admin generating it by hand.
Schedule::call(fn () => Artisan::call('app:generate-rent-bills'))
    ->daily()
    ->name('generate-rent-bills');

// "Your payment is due in a week" — in the morning, not at midnight.
Schedule::call(fn () => Artisan::call('app:send-payment-reminders'))
    ->dailyAt('08:00')
    ->name('send-payment-reminders');

// Lets `php artisan app:doctor` confirm the server's cron job is really running.
Schedule::call(fn () => Cache::put('scheduler:heartbeat', now()->toDateTimeString(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
