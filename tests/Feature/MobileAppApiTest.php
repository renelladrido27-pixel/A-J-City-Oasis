<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Notification;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What the mobile app needs from the API to show the same things as the
 * website: room photos, the tenant's profile picture, and update checks.
 */
class MobileAppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_room_list_includes_each_rooms_photos(): void
    {
        $withPhotos = Room::factory()->create(['room_number' => '101']);
        $withPhotos->images()->create(['path' => 'rooms/a.jpg', 'sort_order' => 1]);
        $withPhotos->images()->create(['path' => 'rooms/b.svg', 'sort_order' => 2]);
        Room::factory()->create(['room_number' => '102']);

        $rooms = collect($this->getJson('/api/rooms')->assertOk()->json('rooms'))->keyBy('number');

        $this->assertSame([asset('storage/rooms/a.jpg'), asset('storage/rooms/b.svg')], $rooms['101']['images']);
        $this->assertSame([], $rooms['102']['images']);
    }

    public function test_the_tenants_lease_includes_the_room_photos(): void
    {
        $lease = Lease::factory()->create();
        $lease->room->images()->create(['path' => 'rooms/mine.jpg', 'sort_order' => 1]);
        Sanctum::actingAs($lease->tenant);

        $this->getJson('/api/lease')->assertOk()
            ->assertJsonPath('lease.room.images.0', asset('storage/rooms/mine.jpg'));
    }

    public function test_a_tenant_can_upload_replace_and_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Multipart, as the app sends it. (create() + MIME: XAMPP's PHP has no GD for image().)
        $this->post('/api/profile/photo', ['photo' => UploadedFile::fake()->create('me.jpg', 300, 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertOk();
        $first = $user->fresh()->photo;
        Storage::disk('public')->assertExists($first);
        $this->getJson('/api/user')->assertJsonPath('user.photo_url', asset('storage/'.$first));

        $this->post('/api/profile/photo', ['photo' => UploadedFile::fake()->create('new.png', 300, 'image/png')], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('public')->assertMissing($first);

        $this->deleteJson('/api/profile/photo')->assertOk()->assertJsonPath('user.photo_url', null);
        $this->assertNull($user->fresh()->photo);
    }

    public function test_profile_photos_must_be_images_and_need_a_login(): void
    {
        $this->postJson('/api/profile/photo')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->post('/api/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_the_app_can_ask_for_the_latest_published_build(): void
    {
        Storage::fake('public');

        // Nothing published yet: build 0 means "no update".
        $this->getJson('/api/app/version')->assertOk()->assertJson(['build' => 0, 'apk_url' => null]);

        Storage::disk('public')->put('app/AJ-City-Oasis.apk', 'apk-bytes');
        Storage::disk('public')->put('app/version.json', json_encode([
            'build' => 3, 'version' => '1.0.2', 'file' => 'AJ-City-Oasis.apk', 'notes' => 'Room photos and profile pictures.',
        ]));

        $this->getJson('/api/app/version')->assertOk()->assertJson([
            'build' => 3,
            'version' => '1.0.2',
            'apk_url' => asset('storage/app/AJ-City-Oasis.apk'),
            'notes' => 'Room photos and profile pictures.',
        ]);
    }

    public function test_the_website_offers_the_app_download_only_once_it_is_published(): void
    {
        Storage::fake('public');

        $this->get('/download-app')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Get the Android app');

        Storage::disk('public')->put('app/AJ-City-Oasis-1.0.1.apk', 'apk-bytes');
        Storage::disk('public')->put('app/version.json', json_encode(['build' => 2, 'version' => '1.0.1', 'file' => 'AJ-City-Oasis-1.0.1.apk']));

        $this->get('/download-app')->assertRedirect(asset('storage/app/AJ-City-Oasis-1.0.1.apk'));
        $this->get('/')->assertOk()->assertSee('Get the Android app');
    }

    public function test_a_tenant_can_mark_one_notification_read_and_unread(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create(['user_id' => $user->id, 'title' => 'Rent due', 'message' => 'Your rent is due.', 'type' => 'payment']);
        Sanctum::actingAs($user);

        $this->postJson("/api/notifications/{$notification->id}/read")->assertOk()->assertJsonPath('notification.is_read', true);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->postJson("/api/notifications/{$notification->id}/unread")->assertOk()->assertJsonPath('notification.is_read', false);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_tenant_cannot_touch_someone_elses_notification(): void
    {
        $notification = Notification::create(['user_id' => User::factory()->create()->id, 'title' => 'Private', 'message' => 'Not yours.', 'type' => 'payment']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/notifications/{$notification->id}/read")->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_version_file_pointing_at_a_missing_apk_is_ignored(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('app/version.json', json_encode(['build' => 9, 'version' => '9.0.0', 'file' => 'missing.apk']));

        $this->getJson('/api/app/version')->assertOk()->assertJson(['build' => 0, 'apk_url' => null]);
    }
}
