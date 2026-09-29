<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(Lease $lease): MaintenanceRequest
    {
        return MaintenanceRequest::create([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'room_id' => $lease->room_id,
            'category' => 'Plumbing',
            'description' => 'Leaking sink',
            'status' => 'pending',
        ]);
    }

    public function test_a_tenant_can_report_an_issue_with_a_photo_and_admins_are_notified(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();

        $this->actingAs($lease->tenant)
            ->post(route('tenant.maintenance-requests.store', $lease), [
                'category' => 'Plumbing',
                'description' => 'Leaking sink',
                // create() + mime type rather than image(): image() needs the GD
                // extension, which XAMPP's PHP doesn't enable by default.
                'photo' => UploadedFile::fake()->create('sink.jpg', 200, 'image/jpeg'),
            ])
            ->assertRedirect(route('tenant.maintenance-requests.index'));

        $request = MaintenanceRequest::sole();
        $this->assertSame('pending', $request->status);
        $this->assertSame($lease->room_id, $request->room_id);
        Storage::disk('public')->assertExists($request->photo);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'maintenance']);
    }

    public function test_a_tenant_cannot_report_an_issue_on_someone_elses_lease(): void
    {
        $lease = Lease::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('tenant.maintenance-requests.store', $lease), ['category' => 'Plumbing', 'description' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    /**
     * Regression: this update crashed with "Unknown column 'scheduled_date'" on
     * databases where a duplicate migration had blocked the later migrations.
     */
    public function test_admin_can_update_status_assignee_and_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs($admin)
            ->put(route('admin.maintenance-requests.update', $request), [
                'status' => 'in_progress',
                'assigned_to' => $staff->id,
                'scheduled_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame($staff->id, $request->assigned_to);
        $this->assertSame(now()->addDays(2)->toDateString(), $request->scheduled_date->toDateString());
        $this->assertNull($request->resolved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $request->tenant_id,
            'message' => 'Your Plumbing request is now in progress.',
        ]);
    }

    public function test_completing_a_request_records_when_it_was_resolved(): void
    {
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs(User::factory()->staff()->create())
            ->put(route('staff.maintenance.update', $request), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertNotNull($request->fresh()->resolved_at);
    }

    public function test_a_tenant_cannot_use_the_admin_update_route(): void
    {
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs($request->tenant)
            ->put(route('admin.maintenance-requests.update', $request), ['status' => 'completed'])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_status_must_be_one_of_the_allowed_values(): void
    {
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.maintenance-requests.update', $request), ['status' => 'done'])
            ->assertSessionHasErrors('status');
    }
}
