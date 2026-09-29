<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:expire-stale-bookings')->daily();
Schedule::command('app:check-overdue-payments')->daily();

// Lets `php artisan app:doctor` confirm the server's cron job is really running.
Schedule::call(fn () => Cache::put('scheduler:heartbeat', now()->toDateTimeString(), now()->addDay()))
    ->everyMinute()
    ->name('scheduler-heartbeat');
