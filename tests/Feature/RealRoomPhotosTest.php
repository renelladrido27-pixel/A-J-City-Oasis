<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Support\StockRoomPhotos;
use Database\Seeders\RoomImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The owner's real photos replace the drawn placeholders: on every room that
 * has no photos of its own, and on the home page.
 */
class RealRoomPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function placeholder(Room $room, int $order = 0): string
    {
        $path = "rooms/room-{$room->id}-{$order}.svg";
        Storage::disk('public')->put($path, '<svg/>');
        $room->images()->create(['path' => $path, 'sort_order' => $order]);

        return $path;
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

    public function test_placeholder_drawings_are_replaced_with_the_real_photos(): void
    {
        $room = Room::factory()->create(['description' => 'Spacious corner room, quiet side of the building.', 'inclusions' => ['Shared CR', 'Cable TV']]);
        $old = [$this->placeholder($room, 0), $this->placeholder($room, 1)];
        $bare = Room::factory()->create(['description' => null]);

        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        foreach ([$room, $bare] as $r) {
            $paths = $r->images()->orderBy('sort_order')->pluck('path')->all();
            $this->assertCount(6, $paths);
            $this->assertSame('rooms/stock/01-loft.jpg', $paths[0]);
            $this->assertSame('rooms/stock/06-building.jpg', $paths[5]);
            foreach ($paths as $path) {
                Storage::disk('public')->assertExists($path);
            }
        }
        // The old drawings are gone from disk, and the made-up details no longer contradict the photos.
        Storage::disk('public')->assertMissing($old);
        $this->assertSame(StockRoomPhotos::DESCRIPTION, $room->fresh()->description);
        $this->assertSame(StockRoomPhotos::INCLUSIONS, $room->fresh()->inclusions);
        // A room with no details isn't given any.
        $this->assertNull($bare->fresh()->description);
    }

    public function test_a_room_with_its_own_uploaded_photos_is_left_alone_and_rerunning_changes_nothing(): void
    {
        $own = Room::factory()->create(['description' => 'Written by the admin.']);
        Storage::disk('public')->put('rooms/uploaded.jpg', 'jpg');
        $own->images()->create(['path' => 'rooms/uploaded.jpg', 'sort_order' => 0]);
        $other = Room::factory()->create();

        $this->artisan('app:use-real-room-photos')->assertSuccessful();
        $this->artisan('app:use-real-room-photos')->assertSuccessful();

        $this->assertSame(['rooms/uploaded.jpg'], $own->images()->pluck('path')->all());
        $this->assertSame('Written by the admin.', $own->fresh()->description);
        $this->assertSame(6, $other->images()->count());
    }

    public function test_removing_a_shared_photo_from_one_room_keeps_it_for_the_others(): void
    {
        [$a, $b] = [Room::factory()->create(), Room::factory()->create()];
        $this->artisan('app:use-real-room-photos');
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

    public function test_a_fresh_install_seeds_rooms_with_the_real_photos(): void
    {
        $rooms = Room::factory()->count(4)->create(['property_id' => Room::factory()->create()->property_id]);

        $this->seed(RoomImageSeeder::class);
        $this->seed(RoomImageSeeder::class);

        $this->assertSame(0, \App\Models\RoomImage::where('path', 'like', '%.svg')->count());
        foreach ($rooms as $room) {
            $this->assertSame(6, $room->images()->count());
        }
        $this->assertSame(3, Room::where('description', StockRoomPhotos::DESCRIPTION)->count());
    }

    public function test_the_home_page_shows_the_building_and_the_units(): void
    {
        Room::factory()->create();

        // Before the photos are published: the building photos (they ship with the site) still show.
        $this->get('/')->assertOk()
            ->assertSee(asset('images/building-front.jpg'))
            ->assertSee(asset('images/building-side.jpg'))
            ->assertDontSee('hero-placeholder.svg');

        $this->artisan('app:use-real-room-photos');

        $this->get('/')->assertOk()
            ->assertSee('Take a look')
            ->assertSee(asset('storage/rooms/stock/01-loft.jpg'))
            ->assertSee(asset('storage/rooms/stock/04-kitchen.jpg'));
    }

    public function test_the_app_and_the_room_pages_get_the_real_photos(): void
    {
        $room = Room::factory()->create(['room_number' => '101']);
        $this->artisan('app:use-real-room-photos');

        $this->getJson('/api/rooms')->assertOk()
            ->assertJsonPath('rooms.0.images.0', asset('storage/rooms/stock/01-loft.jpg'))
            ->assertJsonCount(6, 'rooms.0.images');

        $this->get(route('rooms.show', $room))->assertOk()->assertSee(asset('storage/rooms/stock/01-loft.jpg'));
        $this->get(route('rooms.browse'))->assertOk()->assertSee(asset('storage/rooms/stock/01-loft.jpg'));
    }
}
