<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireStaleBookings extends Command
{
    protected $signature = 'app:expire-stale-bookings';

    protected $description = 'Cancel unpaid bookings past their 7-day move-in-date selection deadline';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->expireStaleBookings();

        $this->info("Expired {$count} stale booking(s).");

        return self::SUCCESS;
    }
}
