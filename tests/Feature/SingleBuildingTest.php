<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Booking;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use App\Services\RoomGenerationService;
use App\Support\BuildingLayout;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoomImageSeeder;
use Database\Seeders\RoomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The real property is one building with 14 rooms (101–107, 201–207) — not
 * the 53 rooms in two properties the system was first seeded with.
 */
class SingleBuildingTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBERS = ['101', '102', '103', '104', '105', '106', '107', '201', '202', '203', '204', '205', '206', '207'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** The old made-up data: 27 rooms in "Main", 26 in "Annex". */
    private function oldLayout(): array
    {
        $main = Property::create(['name' => 'A&J CITY OASIS - Main Building', 'address' => 'Koronadal City']);
        $annex = Property::create(['name' => 'A&J CITY OASIS - Annex Building', 'address' => 'Koronadal City']);
        (new RoomGenerationService)->generate($main, 27, 3500);
        (new RoomGenerationService)->generate($annex, 26, 3500);

        return [$main, $annex];
    }

    private function leaseIn(Room $room): Lease
    {
        $booking = Booking::factory()->confirmed()->create(['room_id' => $room->id]);

        return Lease::factory()->create(['booking_id' => $booking->id]);
    }

    public function test_a_fresh_install_has_one_building_with_14_rooms(): void
    {
        $this->seed([PropertySeeder::class, RoomSeeder::class, RoomImageSeeder::class]);

        $this->assertSame(['A&J CITY OASIS'], Property::pluck('name')->all());
        $this->assertSame(self::NUMBERS, Room::orderBy('room_number')->pluck('room_number')->all());
        $this->assertSame(7, Room::where('floor', 1)->count());
        $this->assertSame(7, Room::where('floor', 2)->count());
        $this->assertSame(14, BuildingLayout::roomCount());
    }

    public function test_an_existing_database_is_reduced_to_the_real_building(): void
    {
        [$main] = $this->oldLayout();
        $this->assertSame(53, Room::count());

        $this->artisan('app:use-single-building')->assertSuccessful();

        $this->assertSame(1, Property::count());
        $this->assertSame('A&J CITY OASIS', $main->fresh()->name);
        $this->assertSame(self::NUMBERS, Room::orderBy('room_number')->pluck('room_number')->all());
        $this->assertSame([$main->id], Room::distinct()->pluck('property_id')->all());
        $this->assertSame(7, Room::where('floor', 2)->count());
        $this->assertSame(0, Room::where('floor', 3)->count());
        // Every room has the real photos, and no photo rows are left pointing at deleted rooms.
        $this->assertSame(14 * 6, RoomImage::count());
    }

    public function test_rooms_with_tenants_are_kept_and_moved_into_the_building(): void
    {
        [$main, $annex] = $this->oldLayout();
        $find = fn (Property $p, string $n) => Room::where('property_id', $p->id)->where('room_number', $n)->firstOrFail();

        $stays = $this->leaseIn($find($main, '101'));
        // Would otherwise be deleted: an Annex room (its number is taken in Main),
        // a third-floor room, and a room past the seventh on its floor.
        $fromAnnex = $this->leaseIn($find($annex, '101'));
        $thirdFloor = $this->leaseIn($find($main, '305'));
        $pastSeven = Booking::factory()->create(['room_id' => $find($main, '109')->id]);

        $this->artisan('app:use-single-building')->assertSuccessful();

        $this->assertSame(14, Room::count());
        $this->assertSame(self::NUMBERS, Room::orderBy('room_number')->pluck('room_number')->all());

        // Nothing a tenant owns was lost.
        foreach ([$stays, $fromAnnex, $thirdFloor] as $lease) {
            $this->assertNotNull($lease->fresh(), 'a lease was deleted');
            $this->assertSame($main->id, (int) $lease->fresh()->room->property_id);
        }
        $this->assertNotNull($pastSeven->fresh());

        $this->assertSame('101', $stays->fresh()->room->room_number);
        // The moved rooms took free numbers, on the right floor.
        $moved = collect([$fromAnnex->fresh()->room, $thirdFloor->fresh()->room, $pastSeven->fresh()->room]);
        $this->assertEqualsCanonicalizing(['102', '103', '104'], $moved->pluck('room_number')->all());
        $this->assertSame([1], $moved->pluck('floor')->unique()->values()->all());
        $this->assertSame('occupied', $fromAnnex->fresh()->room->status);
    }

    public function test_a_dry_run_changes_nothing_and_running_twice_is_harmless(): void
    {
        [, $annex] = $this->oldLayout();
        Announcement::factory()->create(['audience' => 'property', 'property_id' => $annex->id]);

        $this->artisan('app:use-single-building --dry-run')->assertSuccessful();
        $this->assertSame(53, Room::count());
        $this->assertSame(2, Property::count());

        $this->artisan('app:use-single-building')->assertSuccessful();
        $ids = Room::orderBy('id')->pluck('id')->all();
        $this->artisan('app:use-single-building')->assertSuccessful();

        $this->assertSame($ids, Room::orderBy('id')->pluck('id')->all());
        $this->assertSame(1, Property::count());
        // An announcement addressed to the removed property isn't lost with it.
        $this->assertSame(1, Announcement::count());
        $this->assertSame(Property::first()->id, (int) Announcement::first()->property_id);
    }

    public function test_it_refuses_when_more_rooms_have_tenants_than_the_building_holds(): void
    {
        [$main, $annex] = $this->oldLayout();
        Room::whereIn('property_id', [$main->id, $annex->id])->take(15)->get()
            ->each(fn (Room $room) => Booking::factory()->create(['room_id' => $room->id]));

        $this->artisan('app:use-single-building')->assertFailed();

        $this->assertSame(53, Room::count());
        $this->assertSame(2, Property::count());
    }

    public function test_the_site_talks_about_one_building(): void
    {
        $this->seed([PropertySeeder::class, RoomSeeder::class]);
        Room::first()->update(['status' => 'occupied']);

        $this->get('/')->assertOk()
            ->assertSee('14 studio units in Koronadal City')
            ->assertSee('Studio units in one building')
            ->assertSee('Available to book right now')
            ->assertDontSee('properties');

        // With a single property there is nothing to filter by.
        $this->get(route('rooms.browse'))->assertOk()->assertDontSee('All Properties')->assertDontSee('two properties');
    }
}
