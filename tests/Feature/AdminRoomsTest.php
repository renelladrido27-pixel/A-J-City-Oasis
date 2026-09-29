<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lease;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rooms_list_shows_who_occupies_or_has_reserved_each_room(): void
    {
        $admin = User::factory()->admin()->create();

        $occupied = Lease::factory()->create(['start_date' => '2026-10-01']);
        $occupied->tenant->update(['name' => 'Maria Santos']);

        $unpaid = Booking::factory()->create();
        $unpaid->tenant->update(['name' => 'Juan Dela Cruz']);

        $paid = Booking::factory()->confirmed()->create(['move_in_date' => '2026-10-04']);
        $paid->tenant->update(['name' => 'Ana Reyes']);

        Room::factory()->create(['room_number' => '999']);

        $this->actingAs($admin)->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Tenant')
            ->assertSeeInOrder(['Maria Santos', 'Since Oct 01, 2026'])
            ->assertSeeInOrder(['Juan Dela Cruz', 'Reserved', 'awaiting payment'])
            ->assertSeeInOrder(['Ana Reyes', 'Moving in Oct 04, 2026'])
            ->assertSee(route('admin.tenants.show', $occupied->tenant));
    }

    public function test_a_former_tenant_is_not_shown_after_their_lease_ends(): void
    {
        $lease = Lease::factory()->create(['status' => 'ended']);
        $lease->tenant->update(['name' => 'Former Tenant']);
        $lease->room->update(['status' => 'vacant']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertDontSee('Former Tenant');
    }

    public function test_the_tenant_column_does_not_query_once_per_room(): void
    {
        $admin = User::factory()->admin()->create();
        Lease::factory()->count(3)->create();
        $this->actingAs($admin)->get(route('admin.rooms.index'));

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertOk();
        $fewRooms = count(DB::getQueryLog());

        Lease::factory()->count(10)->create();
        DB::flushQueryLog();
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertOk();

        $this->assertSame($fewRooms, count(DB::getQueryLog()));
    }
}
