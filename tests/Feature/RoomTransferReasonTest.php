<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Paper REQ8.1, REQ8.3, REQ22.2: reason + preferred date in, deposits shown, rejection reason out. */
class RoomTransferReasonTest extends TestCase
{
    use RefreshDatabase;

    private function lease(): array
    {
        $from = Room::factory()->create(['status' => 'occupied', 'monthly_rate' => 3000]);
        $to = Room::factory()->create(['status' => 'vacant', 'monthly_rate' => 3500]);

        return [Lease::factory()->create(['room_id' => $from->id]), $to];
    }

    public function test_the_tenant_gives_a_reason_and_preferred_date(): void
    {
        [$lease, $to] = $this->lease();

        $this->actingAs($lease->tenant)->post(route('tenant.room-transfers.store', $lease), ['to_room_id' => $to->id])
            ->assertSessionHasErrors(['preferred_date', 'reason']);
        $this->actingAs($lease->tenant)->post(route('tenant.room-transfers.store', $lease), [
            'to_room_id' => $to->id, 'preferred_date' => today()->subDay()->toDateString(), 'reason' => 'x',
        ])->assertSessionHasErrors('preferred_date');

        $date = today()->addDays(5)->toDateString();
        $this->actingAs($lease->tenant)->post(route('tenant.room-transfers.store', $lease), [
            'to_room_id' => $to->id, 'preferred_date' => $date, 'reason' => 'Need a bigger room.',
        ])->assertSessionHasNoErrors();

        $transfer = RoomTransfer::sole();
        $this->assertSame('Need a bigger room.', $transfer->reason);
        $this->assertSame($date, $transfer->preferred_date->toDateString());

        // Admin sees the reason, the preferred date, the deposits held (2 × ₱3,000) and the difference.
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.room-transfers.index'))
            ->assertSee('Need a bigger room.')->assertSee('₱6,000.00')->assertSee('₱500.00')
            ->assertSee(today()->addDays(5)->format('M d, Y'));
    }

    public function test_the_app_sends_the_preferred_date_too(): void
    {
        [$lease, $to] = $this->lease();
        Sanctum::actingAs($lease->tenant);

        $this->postJson('/api/room-transfers', ['to_room_id' => $to->id, 'reason' => 'Quieter floor', 'preferred_date' => today()->addDay()->toDateString()])
            ->assertCreated()->assertJsonPath('transfer.preferred_date', today()->addDay()->toDateString());
    }

    public function test_rejecting_needs_a_reason_that_reaches_the_tenant(): void
    {
        [$lease, $to] = $this->lease();
        $transfer = RoomTransfer::create([
            'lease_id' => $lease->id, 'from_room_id' => $lease->room_id, 'to_room_id' => $to->id,
            'deposit_adjustment' => 500, 'status' => 'pending', 'requested_at' => now(),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.room-transfers.reject', $transfer))->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending', $transfer->fresh()->status);

        $this->actingAs($admin)->post(route('admin.room-transfers.reject', $transfer), ['rejection_reason' => 'Room is reserved for repairs.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $transfer->fresh()->status);
        $this->assertSame('Room is reserved for repairs.', $transfer->fresh()->rejection_reason);
        $this->assertStringContainsString('Room is reserved for repairs.', $lease->tenant->notifications()->latest('id')->first()->message);

        Sanctum::actingAs($lease->tenant);
        $this->getJson('/api/room-transfers')->assertJsonPath('requests.0.rejection_reason', 'Room is reserved for repairs.');
    }
}
