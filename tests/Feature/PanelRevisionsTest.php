<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Support\PhilippineAddress;
use Database\Seeders\PropertySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Panel revisions: dropdown addresses, unique room numbers per building,
 * newest-first lists, building + address on the transfer list, "Mark as done"
 * only from the scheduled day, and the Terms and Conditions before paying.
 */
class PanelRevisionsTest extends TestCase
{
    use RefreshDatabase;

    private const KORONADAL = [
        'region' => 'Region XII (SOCCSKSARGEN)',
        'province' => 'South Cotabato',
        'city' => 'City of Koronadal',
    ];

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // ── Address: Region → Province → City/Municipality → Barangay ───────────

    public function test_the_address_data_covers_the_whole_country(): void
    {
        $places = PhilippineAddress::places();

        $this->assertCount(17, $places);
        $this->assertSame(1634, collect($places)->flatMap(fn ($r) => collect($r['provinces'])->flatMap(fn ($p) => $p['cities']))->count());
        $this->assertSame('126306000', PhilippineAddress::cityCode(...array_values(self::KORONADAL)));
        $this->assertContains('Santo Niño', PhilippineAddress::barangays('126306000'));
        // A city only matches inside its own province and region.
        $this->assertNull(PhilippineAddress::cityCode('Region XI (Davao Region)', 'South Cotabato', 'City of Koronadal'));
        $this->assertNotNull(PhilippineAddress::cityCode('National Capital Region (NCR)', 'Metro Manila', 'Quezon City'));
    }

    public function test_the_property_form_picks_the_address_from_dropdowns(): void
    {
        $this->actingAs($this->admin())->get(route('admin.properties.create'))->assertOk()
            ->assertSee('<select name="region"', false)
            ->assertSee('<select name="province"', false)
            ->assertSee('<select name="city"', false)
            ->assertSee('<select name="barangay"', false)
            ->assertSee('name="street"', false)
            ->assertSee(asset('data/ph-places.json'))
            ->assertDontSee('name="address"', false);
    }

    public function test_a_property_is_saved_with_its_full_address(): void
    {
        $this->actingAs($this->admin())->post(route('admin.properties.store'), [
            'name' => 'A&J CITY OASIS - East Wing',
            ...self::KORONADAL,
            'barangay' => 'Santo Niño',
            'street' => 'Maglantay Subd.',
        ])->assertRedirect(route('admin.properties.index'));

        $property = Property::sole();
        $this->assertSame('Maglantay Subd., Santo Niño, City of Koronadal, South Cotabato', $property->address);
        $this->assertSame('Region XII (SOCCSKSARGEN)', $property->region);
        $this->assertSame('Santo Niño', $property->barangay);
    }

    public function test_an_address_that_does_not_fit_together_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.properties.store'), [
            'name' => 'X', ...self::KORONADAL, 'city' => 'Davao City', 'street' => 'Somewhere',
        ])->assertSessionHasErrors('city');

        $this->actingAs($admin)->post(route('admin.properties.store'), [
            'name' => 'X', ...self::KORONADAL, 'barangay' => 'Poblacion', 'street' => 'Somewhere',
        ])->assertSessionHasErrors('barangay');

        $this->actingAs($admin)->post(route('admin.properties.store'), ['name' => 'X', ...self::KORONADAL])
            ->assertSessionHasErrors('street');

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_the_barangay_list_is_for_admins_only(): void
    {
        $this->actingAs($this->admin())->getJson(route('admin.places.barangays', '126306000'))->assertOk()
            ->assertJsonFragment(['Santo Niño'])->assertJsonFragment(['Zone II (Pob.)']);

        $this->actingAs(User::factory()->create())->get(route('admin.places.barangays', '126306000'))->assertForbidden();
    }

    public function test_the_edit_form_starts_from_the_saved_address(): void
    {
        $this->seed(PropertySeeder::class);
        $main = Property::where('name', 'A&J CITY OASIS - Main Building')->sole();

        $this->assertSame('Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato', $main->address);
        $this->actingAs($this->admin())->get(route('admin.properties.edit', $main))->assertOk()
            ->assertSee('data-value="Region XII (SOCCSKSARGEN)"', false)
            ->assertSee('data-value="City of Koronadal"', false)
            ->assertSee('data-value="Santo Niño"', false)
            ->assertSee('value="Maglantay Subd., Bo. 2"', false);
    }

    // ── Rooms: identifier, type dropdown ─────────────────────────────────────

    public function test_a_room_number_is_unique_within_its_building(): void
    {
        $admin = $this->admin();
        $main = Property::factory()->create();
        $annex = Property::factory()->create();
        Room::factory()->for($main)->create(['room_number' => '103']);
        $form = fn (Property $p, string $number) => [
            'property_id' => $p->id, 'room_number' => $number, 'floor' => 1, 'type' => 'Studio Loft Type',
            'monthly_rate' => 3500, 'status' => 'vacant',
        ];

        // "Room 103" is the same room number as "103".
        $this->actingAs($admin)->post(route('admin.rooms.store'), $form($main, 'Room 103'))
            ->assertSessionHasErrors(['room_number' => 'That building already has a room with this number.']);
        // Another building can have its own 103.
        $this->actingAs($admin)->post(route('admin.rooms.store'), $form($annex, 'Room 103'))->assertSessionHasNoErrors();

        $this->assertSame('103', Room::where('property_id', $annex->id)->sole()->room_number);
    }

    public function test_the_room_type_is_a_dropdown_with_an_other_option(): void
    {
        $admin = $this->admin();
        $property = Property::factory()->create();

        $this->actingAs($admin)->get(route('admin.rooms.create'))->assertOk()
            ->assertSee('<select name="type"', false)
            ->assertSee('<option value="Studio Loft Type" selected', false)
            ->assertSee('Other…');

        $this->actingAs($admin)->post(route('admin.rooms.store'), [
            'property_id' => $property->id, 'room_number' => '301', 'floor' => 3, 'type' => '__other', 'type_other' => 'Two-Bedroom',
            'monthly_rate' => 5000, 'status' => 'vacant',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Two-Bedroom', Room::sole()->type);
        $this->assertContains('Two-Bedroom', Room::typeOptions());
    }

    // ── Newest first (last in, first out) ───────────────────────────────────

    public function test_admin_lists_show_the_newest_first(): void
    {
        $admin = $this->admin();
        $old = Property::factory()->create(['name' => 'Older Building']);
        $new = Property::factory()->create(['name' => 'Newer Building']);
        Room::factory()->for($old)->create(['room_number' => '901']);
        Room::factory()->for($new)->create(['room_number' => '101']);
        User::factory()->create(['name' => 'Aaron Older']);
        User::factory()->create(['name' => 'Zed Newer']);

        $this->actingAs($admin)->get(route('admin.properties.index'))->assertSeeInOrder(['Newer Building', 'Older Building']);
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertSeeInOrder(['101', '901']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSeeInOrder(['Zed Newer', 'Aaron Older']);
    }

    // ── Transfer list: which building, where, what type ─────────────────────

    public function test_the_transfer_list_names_the_building_its_address_and_the_room_type(): void
    {
        $this->seed(PropertySeeder::class);
        [$main, $annex] = Property::orderBy('id')->get()->all();
        $lease = Lease::factory()->create();
        Room::factory()->for($main)->create(['room_number' => '103', 'type' => 'Studio Loft Type']);
        Room::factory()->for($annex)->create(['room_number' => '103', 'type' => 'Studio Loft Type']);

        $this->actingAs($lease->tenant)->get(route('tenant.room-transfers.create', $lease))->assertOk()
            ->assertSee('<optgroup label="Main Building — Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato">', false)
            ->assertSee('<optgroup label="Annex Building', false)
            ->assertSee('Room 103, Main Building · Studio Loft Type · First Floor')
            ->assertSee('Room 103, Annex Building · Studio Loft Type · First Floor');

        $rooms = $this->getJson('/api/rooms')->assertOk()->json('rooms');
        $main103 = collect($rooms)->firstWhere(fn ($r) => $r['number'] === '103' && $r['building'] === 'Main Building');
        $this->assertSame('Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato', $main103['address']);
        $this->assertSame('Studio Loft Type', $main103['type']);
        // Grouped by building: every Main room comes before every Annex room.
        $buildings = collect($rooms)->pluck('building')->filter(fn ($b) => in_array($b, ['Main Building', 'Annex Building']))->values();
        $this->assertSame($buildings->sort(fn ($a, $b) => $a === 'Main Building' ? -1 : ($b === 'Main Building' ? 1 : 0))->values()->all(), $buildings->all());
    }

    // ── Maintenance: Mark as done only once assigned and on the day ────────

    private function requestFor(Lease $lease): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'lease_id' => $lease->id, 'tenant_id' => $lease->tenant_id, 'room_id' => $lease->room_id,
            'category' => 'Plumbing', 'description' => 'Leaking sink', 'status' => 'pending',
        ]);
    }

    public function test_assigning_needs_a_scheduled_day(): void
    {
        $request = $this->requestFor(Lease::factory()->create());
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => $admin->id])
            ->assertSessionHasErrors('scheduled_date');
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_mark_as_done_appears_only_once_assigned_and_the_day_has_come(): void
    {
        Carbon::setTestNow('2026-10-15 09:00');
        $request = $this->requestFor(Lease::factory()->create());
        $admin = $this->admin();
        $tenant = $request->tenant;
        $page = fn () => $this->actingAs($tenant)->get(route('tenant.maintenance-requests.index'));

        // Not assigned yet: no button, and the server refuses.
        $page()->assertDontSee('Mark as done</button>', false)->assertSee('Waiting to be assigned');
        $this->actingAs($tenant)->post(route('tenant.maintenance-requests.resolve', $request))->assertStatus(422);

        // Assigned for Oct 17: still no button before that day.
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => $admin->id, 'scheduled_date' => '2026-10-17']);
        $page()->assertDontSee('Mark as done</button>', false)->assertSee('Mark as done opens Oct 17, 2026');
        $this->actingAs($tenant)->post(route('tenant.maintenance-requests.resolve', $request))->assertStatus(422);
        Sanctum::actingAs($tenant);
        $this->getJson('/api/maintenance-requests')->assertJsonPath('requests.0.can_mark_done', false);

        // On the day it appears and works.
        Carbon::setTestNow('2026-10-17 08:00');
        $this->getJson('/api/maintenance-requests')->assertJsonPath('requests.0.can_mark_done', true);
        $page()->assertSee('Mark as done</button>', false);
        $this->actingAs($tenant)->post(route('tenant.maintenance-requests.resolve', $request))->assertRedirect();
        $this->assertSame('resolved', $request->fresh()->status);
    }

    public function test_a_day_already_past_still_lets_the_tenant_mark_it_done(): void
    {
        $request = $this->requestFor(Lease::factory()->create());
        $request->update(['assigned_to' => $this->admin()->id, 'scheduled_date' => today()->subDays(3), 'status' => 'in_progress']);

        Sanctum::actingAs($request->tenant);
        $this->postJson("/api/maintenance-requests/{$request->id}/resolve")->assertOk()->assertJsonPath('request.status', 'resolved');
    }

    // ── Terms and Conditions before paying ──────────────────────────────────

    public function test_the_app_must_agree_to_the_terms_before_booking_and_paying(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/rooms/{$room->id}/book", ['move_in_date' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['agreed_to_terms' => 'Please read and agree to the Terms and Conditions before paying.']);
        $this->assertDatabaseCount('bookings', 0);

        $this->postJson("/api/rooms/{$room->id}/book", ['move_in_date' => now()->addDay()->toDateString(), 'agreed_to_terms' => true])
            ->assertCreated();
        $this->assertNotNull(\App\Models\Booking::sole()->agreement_accepted_at);
    }

    public function test_the_app_shows_the_same_terms_as_the_website(): void
    {
        $this->seed(PropertySeeder::class);
        $room = Room::factory()->for(Property::first())->create(['room_number' => '104', 'monthly_rate' => 3500]);

        $terms = $this->getJson("/api/rooms/{$room->id}/terms")->assertOk()
            ->assertJsonPath('title', 'Terms and Conditions')
            ->json('sections');

        $this->assertCount(12, $terms);
        $this->assertSame('Premises', $terms[1]['heading']);
        $this->assertStringContainsString('Room 104, A&J CITY OASIS - Main Building, First Floor (Maglantay Subd., Bo. 2', $terms[1]['body']);
        $this->assertStringContainsString('₱10,500.00', $terms[3]['body']);

        $web = $this->actingAs(User::factory()->create())->get(route('tenant.bookings.create', $room))->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('I have read and agree to the Terms and Conditions above.');
        foreach ($terms as $section) {
            $web->assertSee($section['heading']);
        }
    }
}
