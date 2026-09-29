<?php

namespace Tests\Feature;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Announcements reach everyone, one property, one floor of a property, or a
 * single tenant — the same rules on the web and in the mobile app.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private Property $main;

    private Property $annex;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Property::factory()->create(['name' => 'Main Building']);
        $this->annex = Property::factory()->create(['name' => 'Annex']);

        $room = Room::factory()->for($this->main)->create(['floor' => 2]);
        $this->tenant = Lease::factory()
            ->for(\App\Models\Booking::factory()->confirmed()->state(['room_id' => $room->id]))
            ->create()
            ->tenant;
    }

    private function seedAnnouncements(): void
    {
        $other = User::factory()->create();
        Announcement::factory()->create(['title' => 'Everyone', 'audience' => 'all']);
        Announcement::factory()->create(['title' => 'Main', 'audience' => 'property', 'property_id' => $this->main->id]);
        Announcement::factory()->create(['title' => 'Annex', 'audience' => 'property', 'property_id' => $this->annex->id]);
        Announcement::factory()->create(['title' => 'Main F2', 'audience' => 'floor', 'property_id' => $this->main->id, 'floor' => 2]);
        Announcement::factory()->create(['title' => 'Main F3', 'audience' => 'floor', 'property_id' => $this->main->id, 'floor' => 3]);
        Announcement::factory()->create(['title' => 'Just me', 'audience' => 'tenant', 'tenant_id' => $this->tenant->id]);
        Announcement::factory()->create(['title' => 'Someone else', 'audience' => 'tenant', 'tenant_id' => $other->id]);
    }

    public function test_the_mobile_api_returns_only_announcements_meant_for_the_tenant(): void
    {
        $this->seedAnnouncements();
        Sanctum::actingAs($this->tenant);

        $titles = collect($this->getJson('/api/announcements')->assertOk()->json('announcements'))->pluck('title');

        $this->assertEqualsCanonicalizing(['Everyone', 'Main', 'Main F2', 'Just me'], $titles->all());
    }

    public function test_a_tenant_without_a_lease_only_sees_announcements_for_everyone(): void
    {
        $this->seedAnnouncements();
        Sanctum::actingAs(User::factory()->create());

        $titles = collect($this->getJson('/api/announcements')->json('announcements'))->pluck('title');

        $this->assertSame(['Everyone'], $titles->all());
    }

    public function test_the_announcements_api_requires_login(): void
    {
        $this->getJson('/api/announcements')->assertUnauthorized();
    }

    public function test_posting_a_floor_announcement_notifies_and_emails_only_that_floor(): void
    {
        Mail::fake();
        $annexTenant = Lease::factory()->create()->tenant; // a room in a different property

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.announcements.store'), [
                'title' => 'Water interruption',
                'body' => 'No water on floor 2 tomorrow 8-10 AM.',
                'audience' => 'floor',
                'property_id' => $this->main->id,
                'floor' => 2,
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('notifications', ['user_id' => $this->tenant->id, 'title' => 'Water interruption']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $annexTenant->id, 'title' => 'Water interruption']);

        Mail::assertSent(AnnouncementMail::class, 1);
        Mail::assertSent(AnnouncementMail::class, fn ($m) => $m->hasTo($this->tenant->email));
    }

    public function test_only_admins_can_post_announcements(): void
    {
        $this->actingAs($this->tenant)
            ->post(route('admin.announcements.store'), ['title' => 'x', 'body' => 'x', 'audience' => 'all'])
            ->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }
}
