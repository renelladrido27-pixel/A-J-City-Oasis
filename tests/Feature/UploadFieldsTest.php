<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every upload field uses the shared <x-photo-upload> box (or, for profile
 * pictures, the avatar picker) and the server enforces the same file rules.
 * UploadedFile::fake()->create() + MIME type is used because XAMPP's PHP has
 * no GD extension for ->image().
 */
class UploadFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function jpg(string $name = 'photo.jpg', int $kb = 300): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'image/jpeg');
    }

    public function test_admin_can_upload_several_room_photos_at_once(): void
    {
        Storage::fake('public');
        $property = Property::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rooms.store'), [
                'property_id' => $property->id,
                'room_number' => '301',
                'floor' => 3,
                'type' => 'single',
                'monthly_rate' => 3500,
                'status' => 'vacant',
                'images' => [$this->jpg('front.jpg'), $this->jpg('bed.jpg'), UploadedFile::fake()->create('cr.webp', 200, 'image/webp')],
            ])
            ->assertRedirect();

        $room = Room::where('room_number', '301')->sole();
        $this->assertCount(3, $room->images);
        $room->images->each(fn ($image) => Storage::disk('public')->assertExists($image->path));
    }

    public function test_room_photos_reject_non_images_and_oversized_files(): void
    {
        Storage::fake('public');
        $room = Room::factory()->create();
        $admin = User::factory()->admin()->create();
        $base = ['property_id' => $room->property_id, 'room_number' => $room->room_number, 'floor' => 1, 'type' => 'single', 'monthly_rate' => 3500, 'status' => 'vacant'];

        $this->actingAs($admin)->put(route('admin.rooms.update', $room), $base + ['images' => [UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf')]])
            ->assertSessionHasErrors('images.0');
        $this->actingAs($admin)->put(route('admin.rooms.update', $room), $base + ['images' => [$this->jpg('huge.jpg', 5000)]])
            ->assertSessionHasErrors('images.0');

        $this->assertCount(0, $room->images()->get());
    }

    public function test_the_lease_document_accepts_a_pdf(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.leases.document.store', $lease), ['document' => UploadedFile::fake()->create('signed-lease.pdf', 900, 'application/pdf')])
            ->assertRedirect();

        Storage::disk('public')->assertExists($lease->fresh()->document);
    }

    public function test_the_lease_document_rejects_other_file_types(): void
    {
        $lease = Lease::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.leases.document.store', $lease), ['document' => UploadedFile::fake()->create('lease.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertSessionHasErrors('document');

        $this->assertNull($lease->fresh()->document);
    }

    public function test_profile_photos_must_be_jpg_png_or_webp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'photo' => UploadedFile::fake()->create('me.gif', 100, 'image/gif')])
            ->assertSessionHasErrors('photo');
    }

    public function test_the_admin_forms_use_the_upload_box(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $lease = Lease::factory()->create();

        $this->actingAs($admin)->get(route('admin.rooms.create'))
            ->assertOk()->assertSee('data-photo-upload', false)->assertSee('data-multiple="1"', false)->assertSee('Add photos');
        $this->actingAs($admin)->get(route('admin.rooms.edit', $room))
            ->assertOk()->assertSee('data-multiple="1"', false);
        $this->actingAs($admin)->get(route('admin.tenants.show', $lease->tenant))
            ->assertOk()->assertSee('data-accept="application/pdf,image/jpeg,image/png"', false)->assertSee('Choose the signed agreement');
    }

    public function test_sign_up_shows_a_photo_error(): void
    {
        $this->post('/register', [
            'intent' => 'signup',
            'name' => 'New Tenant',
            'email' => 'new@example.com',
            'phone' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'photo' => UploadedFile::fake()->create('me.gif', 100, 'image/gif'),
        ])->assertSessionHasErrors('photo');

        $this->followingRedirects()->get('/register')->assertSee('id="avatarError"', false);
    }
}
