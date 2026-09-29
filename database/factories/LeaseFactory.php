<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active lease. Tenant and room are taken from the booking so the three
 * always agree (as BookingService::createLeaseForBooking() guarantees).
 *
 * @extends Factory<Lease>
 */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->confirmed(),
            'tenant_id' => fn (array $attrs) => Booking::find($attrs['booking_id'])->user_id,
            'room_id' => fn (array $attrs) => Booking::find($attrs['booking_id'])->room_id,
            'start_date' => now(),
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Lease $lease) => $lease->room->update(['status' => 'occupied']));
    }
}
