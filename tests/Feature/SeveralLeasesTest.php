<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A tenant renting two rooms: each room shows its own next due date — Room
 * 202 (moved in Oct 27) is due Nov 27 even though Room 102's Nov 8 bill comes
 * first — and every bill says which room it is for.
 */
class SeveralLeasesTest extends TestCase
{
    use RefreshDatabase;

    private function leaseFor(User $tenant, string $number, string $building, string $start): Lease
    {
        $property = Property::firstOrCreate(['name' => "A&J CITY OASIS - {$building}"], ['address' => 'Koronadal City']);
        $room = Room::factory()->for($property)->create(['room_number' => $number]);
        $booking = Booking::factory()->confirmed()->create(['user_id' => $tenant->id, 'room_id' => $room->id, 'move_in_date' => $start]);

        return Lease::factory()->create(['booking_id' => $booking->id, 'tenant_id' => $tenant->id, 'room_id' => $room->id, 'start_date' => $start]);
    }

    public function test_each_room_shows_its_own_next_due(): void
    {
        $this->travelTo('2026-10-28 09:00:00');
        $tenant = User::factory()->create();
        $annex102 = $this->leaseFor($tenant, '102', 'Annex Building', '2026-10-08');
        $main202 = $this->leaseFor($tenant, '202', 'Main Building', '2026-10-27');
        Payment::factory()->create(['lease_id' => $annex102->id, 'type' => 'rent', 'due_date' => '2026-11-08', 'amount' => 3500]);
        Payment::factory()->create(['lease_id' => $main202->id, 'type' => 'rent', 'due_date' => '2026-11-27', 'amount' => 3500]);
        Sanctum::actingAs($tenant);

        $res = $this->getJson('/api/lease')->assertOk();
        // My rental opens on the newest lease, with its own bill.
        $res->assertJsonPath('lease.room.number', '202')
            ->assertJsonPath('lease.next_due.date', '2026-11-27')
            ->assertJsonPath('lease.balance', 3500);
        $leases = collect($res->json('leases'))->keyBy('room.number');
        $this->assertCount(2, $leases);
        $this->assertSame('2026-11-08', $leases['102']['next_due']['date']);
        $this->assertSame('Annex Building', $leases['102']['room']['building']);

        // The Pay tab's overall next due is still the earliest bill, and each
        // bill names its room.
        $pay = $this->getJson('/api/payments')->assertOk()->assertJsonPath('next_due_date', '2026-11-08');
        $this->assertEqualsCanonicalizing(
            ['Room 102 · Annex Building', 'Room 202 · Main Building'],
            collect($pay->json('history'))->pluck('room')->all(),
        );
    }

    public function test_a_room_with_nothing_owed_says_so(): void
    {
        $tenant = User::factory()->create();
        $this->leaseFor($tenant, '202', 'Main Building', '2026-10-27');
        Sanctum::actingAs($tenant);

        $this->getJson('/api/lease')->assertOk()
            ->assertJsonPath('lease.next_due', null)
            ->assertJsonPath('lease.balance', 0);
    }
}
