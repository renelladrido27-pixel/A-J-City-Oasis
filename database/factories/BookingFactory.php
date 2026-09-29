<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending-payment booking with the 3-month upfront split, matching what
 * BookingService::createBooking() produces.
 *
 * @extends Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'room_id' => Room::factory()->state(['status' => 'reserved']),
            'booked_at' => now(),
            'move_in_deadline' => now()->addDays(7),
            'move_in_date' => now()->addDays(3),
            'status' => 'pending_payment',
            'advance_amount' => 3500,
            'deposit_amount' => 3500,
            'security_amount' => 3500,
            'total_amount' => 10500,
            'agreement_accepted_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }
}
