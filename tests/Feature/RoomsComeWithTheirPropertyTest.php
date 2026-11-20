<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomsComeWithTheirPropertyTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = ['region' => 'Region XII (SOCCSKSARGEN)', 'province' => 'South Cotabato', 'city' => 'City of Koronadal', 'street' => 'Maglantay Subd.'];

    public function test_a_new_property_must_come_with_its_rooms(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.properties.store'), ['name' => 'Annex', ...self::ADDRESS])
            ->assertSessionHasErrors(['room_count', 'monthly_rate']);
        $this->actingAs($admin)->post(route('admin.properties.store'), ['name' => 'Annex', ...self::ADDRESS, 'room_count' => 0, 'monthly_rate' => 3500])
            ->assertSessionHasErrors('room_count');
        $this->assertDatabaseCount('properties', 0);

        $this->actingAs($admin)->post(route('admin.properties.store'), ['name' => 'Annex', ...self::ADDRESS, 'room_count' => 12, 'monthly_rate' => 3500])
            ->assertSessionHasNoErrors();
        $this->assertSame(12, Property::sole()->rooms()->count());
    }

    public function test_an_existing_property_does_not_gain_rooms(): void
    {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        Room::factory()->for($property)->create(['room_number' => '101']);

        $this->actingAs($admin)->get(route('admin.properties.edit', $property))->assertOk()->assertDontSee('name="room_count"', false);
        $this->actingAs($admin)->put(route('admin.properties.update', $property), ['name' => 'Renamed', ...self::ADDRESS, 'room_count' => 5, 'monthly_rate' => 3500])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $property->fresh()->name);
        $this->assertSame(1, $property->rooms()->count());

        // No standalone "Add Room" either.
        $this->assertFalse(\Route::has('admin.rooms.create'));
        $this->assertFalse(\Route::has('admin.rooms.store'));
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertOk()->assertDontSee('Add Room');
    }

    public function test_the_soa_filters_paid_and_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();
        foreach (['paid' => 1000, 'pending' => 2000, 'overdue' => 3000] as $status => $amount) {
            Payment::factory()->create(['lease_id' => $lease->id, 'status' => $status, 'amount' => $amount, 'due_date' => '2026-11-10']);
        }
        $amounts = fn (array $q) => array_column($this->actingAs($admin)
            ->get(route('admin.reports.index', ['report' => 'payment', 'from' => '2026-11-01', 'to' => '2026-11-30'] + $q))
            ->assertOk()->viewData('result')['rows'], 3);

        $this->assertCount(3, $amounts([]));
        $this->assertSame(['1,000.00'], $amounts(['status' => 'paid']));
        $this->assertEqualsCanonicalizing(['2,000.00', '3,000.00'], $amounts(['status' => 'pending'])); // overdue is unpaid too
        $this->assertCount(3, $amounts(['status' => 'bogus']));

        // Paid bills show the receipt number printed on their receipt; unpaid ones have none.
        $result = $this->actingAs($admin)->get(route('admin.reports.index', ['report' => 'payment', 'from' => '2026-11-01', 'to' => '2026-11-30']))->assertSee('Gross')->viewData('result');
        $rows = $result['rows'];
        $this->assertSame('6,000.00', $result['total'][3]); // gross of all three bills
        $this->assertNotContains('Due Date', $result['columns']);
        $paid = Payment::where('status', 'paid')->sole();
        $this->assertEqualsCanonicalizing(['#'.str_pad((string) $paid->id, 6, '0', STR_PAD_LEFT), '—', '—'], array_column($rows, 5));
    }
}
