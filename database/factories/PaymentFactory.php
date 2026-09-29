<?php

namespace Database\Factories;

use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending monthly rent payment on an active lease.
 *
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'type' => 'rent',
            'amount' => 3500,
            'due_date' => now()->addMonth()->startOfMonth(),
            'status' => 'pending',
        ];
    }
}
