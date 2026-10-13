<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Services\RoomGenerationService;
use App\Support\StockRoomPhotos;
use Database\Seeders\PropertySeeder;
use Database\Seeders\RoomImageSeeder;
use Database\Seeders\RoomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The owner's real photos are shown on the twelve photographed rooms — the
 * first six on floors 1 and 2 of the first building — and on the home page.
 * Every other room keeps a drawn placeholder.
 */
class RealRoomPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTOGRAPHED = ['101', '102', '103', '104', '105', '106', '201', '202', '203', '204', '205', '206'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** 53 rooms: 27 in Main (three floors), 26 in Annex. */
    private function buildings(): array
    {
        $this->seed([PropertySeeder::class, RoomSeeder::class]);

        return Property::orderBy('id')->get()->all();
    }

    private function room(Property $property, string $number): Room
    {
        return Room::where('property_id', $property->id)->where('room_number', $number)->firstOrFail();
    }

    private function paths(Room $room): array
    {
        return $room->images()->orderBy('sort_order')->pluck('path')->all();
    }

    public function test_the_photo_set_is_in_the_repo_and_small_enough_for_the_web(): void
    {
        $files = StockRoomPhotos::sourceFiles();

        $this->assertCount(6, $files);
        foreach ([...$files, public_path('images/building-front.jpg'), public_path('images/building-side.jpg')] as $file) {
            $this->assertFileExists($file);
            $this->assertLessThan(400 * 1024, filesize($file), basename($file).' is too heavy for a web page');
            [$width, $height] = getimagesize($file);
            $this->assertLessThanOrEqual(1600, max($width, $height));
        }
    }

    public function test_the_original_53_rooms_in_two_buildings_are_seeded(): void
    {
        [$main, $annex] = $this->buildings();

        $this->assertSame(53, Room::count());
        $this->assertSame(27, $main->rooms()->count());
        $this->assertSame(26, $annex->rooms()->count());
        $this->assertSame('A&J CITY OASIS - Main Building', $main->name);
        $this->assertSame('A&J CITY OASIS - Annex Building', $annex->name);
    }

    public function test_only_the_first_six_rooms_on_floors_one_and_two_of_the_first_building_get_real_photos(): void
    {
        [$main, $annex] = $this->buildings();

        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        $withPhotos = Room::whereHas('images', fn ($q) => $q->where('path', 'like', 'rooms/stock/%'))->get();
        $this->assertSame(12, $withPhotos->count());
        $this->assertSame([$main->id], $withPhotos->pluck('property_id')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(self::PHOTOGRAPHED, $withPhotos->pluck('room_number')->all());

        // A photographed room: the six real photos, in order, and details that match them.
        $photographed = $this->room($main, '203');
        $this->assertSame([
            'rooms/stock/01-loft.jpg', 'rooms/stock/02-entry.jpg', 'rooms/stock/03-kitchen-bath.jpg',
            'rooms/stock/04-kitchen.jpg', 'rooms/stock/05-bathroom.jpg', 'rooms/stock/06-building.jpg',
        ], $this->paths($photographed));
        $this->assertSame(StockRoomPhotos::DESCRIPTION, $photographed->description);
        $this->assertSame(StockRoomPhotos::INCLUSIONS, $photographed->inclusions);

        // Everything else — the 7th room onward, the third floor, the whole Annex — has one drawing, no real photo.
        foreach ([$this->room($main, '107'), $this->room($main, '210'), $this->room($main, '301'), $this->room($annex, '101'), $this->room($annex, '206')] as $room) {
            $this->assertSame(["rooms/room-{$room->id}-0.svg"], $this->paths($room), "room {$room->room_number}");
            Storage::disk('public')->assertExists("rooms/room-{$room->id}-0.svg");
            $this->assertNull($room->description);
        }
        $this->assertSame(41, Room::whereHas('images', fn ($q) => $q->where('path', 'like', '%.svg'))->count());
        $this->assertSame(12 * 6 + 41, RoomImage::count());
    }

    public function test_rooms_that_wrongly_had_the_real_photos_go_back_to_a_drawing(): void
    {
        [$main, $annex] = $this->buildings();
        // The state after the photos were first put on every room.
        $paths = StockRoomPhotos::publish();
        Room::all()->each(function (Room $room) use ($paths) {
            foreach ($paths as $order => $path) {
                $room->images()->create(['path' => $path, 'sort_order' => $order]);
            }
        });

        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        $this->assertSame(["rooms/room-{$this->room($annex, '101')->id}-0.svg"], $this->paths($this->room($annex, '101')));
        $this->assertSame(["rooms/room-{$this->room($main, '305')->id}-0.svg"], $this->paths($this->room($main, '305')));
        $this->assertCount(6, $this->paths($this->room($main, '101')));
        $this->assertSame(12, Room::whereHas('images', fn ($q) => $q->where('path', 'like', 'rooms/stock/%'))->count());
        // The shared files are still there for the twelve rooms that use them.
        Storage::disk('public')->assertExists('rooms/stock/01-loft.jpg');
    }

    public function test_a_room_with_its_own_uploaded_photos_is_left_alone_and_rerunning_changes_nothing(): void
    {
        [$main, $annex] = $this->buildings();
        foreach ([$this->room($main, '102'), $this->room($annex, '103')] as $own) {
            Storage::disk('public')->put("rooms/uploaded-{$own->id}.jpg", 'jpg');
            $own->images()->create(['path' => "rooms/uploaded-{$own->id}.jpg", 'sort_order' => 0]);
            $own->update(['description' => 'Written by the admin.']);
        }

        $this->artisan('app:use-real-room-photos')->assertSuccessful();
        $before = RoomImage::orderBy('id')->pluck('path', 'id')->all();
        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        $this->assertSame($before, RoomImage::orderBy('id')->pluck('path', 'id')->all());
        foreach ([$this->room($main, '102'), $this->room($annex, '103')] as $own) {
            $this->assertSame(["rooms/uploaded-{$own->id}.jpg"], $this->paths($own));
            $this->assertSame('Written by the admin.', $own->description);
        }
    }

    public function test_made_up_details_on_a_photographed_room_are_replaced_with_what_the_photos_show(): void
    {
        [$main, $annex] = $this->buildings();
        $madeUp = ['description' => 'Spacious corner room, quiet side of the building.', 'inclusions' => ['Shared CR', 'Cable TV']];
        $this->room($main, '101')->update($madeUp);
        $this->room($annex, '101')->update($madeUp);

        $this->artisan('app:use-real-room-photos');

        $this->assertSame(StockRoomPhotos::INCLUSIONS, $this->room($main, '101')->inclusions);
        // A room without the photos keeps whatever it had.
        $this->assertSame(['Shared CR', 'Cable TV'], $this->room($annex, '101')->inclusions);
    }

    public function test_removing_a_shared_photo_from_one_room_keeps_it_for_the_others(): void
    {
        [$main] = $this->buildings();
        $this->artisan('app:use-real-room-photos');
        [$a, $b] = [$this->room($main, '101'), $this->room($main, '102')];
        $admin = User::factory()->admin()->create();
        $image = $a->images()->orderBy('sort_order')->first();

        $this->actingAs($admin)->delete(route('admin.rooms.images.destroy', [$a, $image]))->assertRedirect();

        $this->assertSame(5, $a->images()->count());
        $this->assertSame(6, $b->images()->count());
        Storage::disk('public')->assertExists($image->path);

        // Deleting a whole room doesn't take the shared files with it either.
        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $a))->assertRedirect();
        Storage::disk('public')->assertExists('rooms/stock/02-entry.jpg');

        // A photo only one room uses is really deleted.
        Storage::disk('public')->put('rooms/only-b.jpg', 'jpg');
        $own = $b->images()->create(['path' => 'rooms/only-b.jpg', 'sort_order' => 9]);
        $this->actingAs($admin)->delete(route('admin.rooms.images.destroy', [$b, $own]));
        Storage::disk('public')->assertMissing('rooms/only-b.jpg');
    }

    public function test_a_fresh_install_is_seeded_the_same_way(): void
    {
        $this->seed([PropertySeeder::class, RoomSeeder::class, RoomImageSeeder::class]);
        $this->seed(RoomImageSeeder::class);

        $this->assertSame(12, Room::whereHas('images', fn ($q) => $q->where('path', 'like', 'rooms/stock/%'))->count());
        $this->assertSame(41, Room::whereHas('images', fn ($q) => $q->where('path', 'like', '%.svg'))->count());
        $this->assertSame(12, Room::where('description', StockRoomPhotos::DESCRIPTION)->count());
    }

    public function test_a_building_with_fewer_rooms_per_floor_just_uses_what_is_there(): void
    {
        $property = Property::create(['name' => 'Small', 'address' => 'Koronadal City']);
        (new RoomGenerationService)->generate($property, 8, 3500, roomsPerFloor: 4);

        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        $this->assertSame(8, Room::whereHas('images', fn ($q) => $q->where('path', 'like', 'rooms/stock/%'))->count());
    }

    public function test_the_home_page_shows_the_building_the_units_and_photographed_rooms_first(): void
    {
        $this->buildings();
        $this->artisan('app:use-real-room-photos');

        $response = $this->get('/')->assertOk()
            ->assertSee(asset('images/building-front.jpg'))
            ->assertSee(asset('images/building-side.jpg'))
            ->assertDontSee('hero-placeholder.svg')
            ->assertSee('Take a look')
            ->assertSee('53 rooms across 2 properties')
            ->assertSee(asset('storage/rooms/stock/04-kitchen.jpg'));

        // The three featured rooms are ones with real photos, not drawings.
        $this->assertSame(0, substr_count($response->getContent(), '-0.svg'));
    }

    public function test_the_app_and_the_room_pages_get_the_real_photos(): void
    {
        [$main, $annex] = $this->buildings();
        $this->artisan('app:use-real-room-photos');

        $rooms = collect($this->getJson('/api/rooms')->assertOk()->json('rooms'));
        $photographed = $rooms->first(fn ($r) => $r['id'] === $this->room($main, '101')->id);
        $drawn = $rooms->first(fn ($r) => $r['id'] === $this->room($annex, '101')->id);

        $this->assertSame(asset('storage/rooms/stock/01-loft.jpg'), $photographed['images'][0]);
        $this->assertCount(6, $photographed['images']);
        $this->assertSame([asset("storage/rooms/room-{$drawn['id']}-0.svg")], $drawn['images']);

        $this->get(route('rooms.show', $this->room($main, '101')))->assertOk()->assertSee(asset('storage/rooms/stock/01-loft.jpg'));
        $this->get(route('rooms.show', $this->room($annex, '101')))->assertOk()->assertDontSee('rooms/stock/');
    }
}
