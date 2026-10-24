<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression (live, Oct 24): an old transfer request from Annex 101 was
 * approved after its tenant had already moved elsewhere — seconds after
 * another tenant moved INTO Annex 101 — and emptied Annex 101, which then
 * showed as available while someone lived there.
 */
class RoomTransferStaleRequestTest extends TestCase
{
    use RefreshDatabase;

    private function request(Lease $lease, Room $from, Room $to): RoomTransfer
    {
        return RoomTransfer::create([
            'lease_id' => $lease->id, 'from_room_id' => $from->id, 'to_room_id' => $to->id,
            'deposit_adjustment' => 0, 'status' => 'pending', 'requested_at' => now(),
        ]);
    }

    private function occupiedLease(Room $room): Lease
    {
        $lease = Lease::factory()->create(['room_id' => $room->id]);
        $room->update(['status' => 'occupied']);

        return $lease;
    }

    public function test_an_out_of_date_request_cannot_empty_someone_elses_room(): void
    {
        $admin = User::factory()->admin()->create();
        $annex101 = Room::factory()->create(['status' => 'vacant']);
        $main103 = Room::factory()->create(['status' => 'vacant']);
        $main307 = Room::factory()->create(['status' => 'vacant']);
        $main201 = Room::factory()->create(['status' => 'vacant']);

        // Nell lives in Annex 101 and asks (Oct 13) to move to Main 307…
        $nell = $this->occupiedLease($annex101);
        $old = $this->request($nell, $annex101, $main307);
        // …then asks again and is moved to Main 103 instead (the old request stays waiting).
        $old->update(['status' => 'rejected']); // keep it out of the way while approving the second
        $second = $this->request($nell, $annex101, $main103);
        $this->actingAs($admin)->post(route('admin.room-transfers.approve', $second))->assertSessionHasNoErrors();
        $old->update(['status' => 'pending']); // as it was on live: still waiting

        // Kurt moves from Main 201 into the now-empty Annex 101.
        $kurt = $this->occupiedLease($main201);
        $kurtsMove = $this->request($kurt, $main201, $annex101);
        $this->actingAs($admin)->post(route('admin.room-transfers.approve', $kurtsMove))->assertSessionHasNoErrors();
        $this->assertSame('occupied', $annex101->fresh()->status);

        // The admin sees the old request is out of date, and approving it is refused.
        $this->actingAs($admin)->get(route('admin.room-transfers.index'))->assertSee('Out of date');
        $this->actingAs($admin)->post(route('admin.room-transfers.approve', $old))
            ->assertSessionHasErrors(['transfer' => 'This request is out of date: the tenant is no longer in Room '.$annex101->room_number.'. Reject it instead.']);

        $this->assertSame('occupied', $annex101->fresh()->status);
        $this->assertSame('occupied', $main103->fresh()->status);
        $this->assertSame('vacant', $main307->fresh()->status);
        $this->assertSame($main103->id, $nell->fresh()->room_id);
        $this->assertSame($annex101->id, $kurt->fresh()->room_id);
        $this->assertSame('pending', $old->fresh()->status);

        // And Annex 101 isn't offered to anyone.
        $this->assertNotContains($annex101->id, collect($this->getJson('/api/rooms')->json('rooms'))->pluck('id'));
    }

    public function test_approving_one_request_closes_the_tenants_other_waiting_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $from = Room::factory()->create();
        [$a, $b] = Room::factory()->count(2)->create(['status' => 'vacant']);
        $lease = $this->occupiedLease($from);
        $first = $this->request($lease, $from, $a);
        $other = $this->request($lease, $from, $b);

        $this->actingAs($admin)->post(route('admin.room-transfers.approve', $first))->assertSessionHasNoErrors();

        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('rejected', $other->fresh()->status);
        $this->assertSame('vacant', $from->fresh()->status);
        $this->assertSame('occupied', $a->fresh()->status);
        $this->assertSame('vacant', $b->fresh()->status);
    }

    public function test_a_tenant_can_only_have_one_request_waiting(): void
    {
        $from = Room::factory()->create();
        [$a, $b] = Room::factory()->count(2)->create(['status' => 'vacant']);
        $lease = $this->occupiedLease($from);

        $this->actingAs($lease->tenant)->post(route('tenant.room-transfers.store', $lease), ['to_room_id' => $a->id])->assertRedirect();
        $this->actingAs($lease->tenant)->post(route('tenant.room-transfers.store', $lease), ['to_room_id' => $b->id])->assertStatus(422);

        Sanctum::actingAs($lease->tenant);
        $this->postJson('/api/room-transfers', ['to_room_id' => $b->id])->assertStatus(422)
            ->assertJsonPath('message', 'You already have a transfer request waiting for the admin. Wait for it to be approved or rejected first.');
        $this->assertSame(1, RoomTransfer::count());
    }

    public function test_room_statuses_are_put_right_daily(): void
    {
        $livedIn = Room::factory()->create(['status' => 'vacant']);
        Lease::factory()->create(['room_id' => $livedIn->id]);
        $empty = Room::factory()->create(['status' => 'occupied']);
        $repairs = Room::factory()->create(['status' => 'maintenance']);

        $this->artisan('app:sync-room-status')->expectsOutputToContain('Fixed 2 room(s).')->assertSuccessful();

        $this->assertSame('occupied', $livedIn->fresh()->status);
        $this->assertSame('vacant', $empty->fresh()->status);
        $this->assertSame('maintenance', $repairs->fresh()->status);
    }
}
