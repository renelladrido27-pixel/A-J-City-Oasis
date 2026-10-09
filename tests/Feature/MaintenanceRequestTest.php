<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

    public function test_the_issue_photo_must_be_a_jpg_png_or_webp_image(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();

        $this->actingAs($lease->tenant)
            ->post(route('tenant.maintenance-requests.store', $lease), [
                'category' => 'Plumbing',
                'description' => 'Leaking sink',
                'photo' => UploadedFile::fake()->create('sink.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    public function test_the_mobile_app_can_upload_an_issue_photo(): void
    {
        Storage::fake('public');
        $lease = Lease::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($lease->tenant);

        // Multipart, exactly as the app's ApiClient.postMultipart sends it.
        $this->post('/api/maintenance-requests', [
            'category' => 'Plumbing',
            'description' => 'Leaking sink',
            'photo' => UploadedFile::fake()->create('IMG_2031.jpg', 800, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        Storage::disk('public')->assertExists(MaintenanceRequest::sole()->photo);
    }

    public function test_the_report_form_uses_the_photo_upload_component(): void
    {
        $lease = Lease::factory()->create();

        $this->actingAs($lease->tenant)->get(route('tenant.maintenance-requests.create', $lease))
            ->assertOk()
            ->assertSee('data-photo-upload', false)
            ->assertSee('Take a photo or choose one');
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
    public function test_admin_assigns_who_and_when_and_the_request_becomes_in_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs($admin)
            ->put(route('admin.maintenance-requests.update', $request), [
                'assigned_to' => $staff->id,
                'scheduled_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Maintenance request assigned.');

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame($staff->id, $request->assigned_to);
        $this->assertSame(now()->addDays(2)->toDateString(), $request->scheduled_date->toDateString());
        $this->assertNull($request->resolved_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $request->tenant_id,
            'title' => 'Maintenance request updated',
        ]);
    }

    public function test_admin_and_staff_cannot_set_the_status_themselves(): void
    {
        $request = $this->requestFor(Lease::factory()->create());
        $staff = User::factory()->staff()->create();

        // A status sent anyway (e.g. from an old page) is ignored.
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.maintenance-requests.update', $request), ['status' => 'resolved'])
            ->assertRedirect();
        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($staff)
            ->put(route('staff.maintenance.update', $request), ['status' => 'resolved', 'assigned_to' => $staff->id])
            ->assertRedirect();
        $this->assertSame('in_progress', $request->fresh()->status);
        $this->assertNull($request->fresh()->resolved_at);

        // The page offers assigning only.
        $this->actingAs($staff)->get(route('staff.maintenance.index'))->assertOk()
            ->assertSee('name="assigned_to"', false)
            ->assertDontSee('name="status"', false)
            ->assertSee('Waiting for the tenant to confirm');
    }

    public function test_unassigning_puts_the_request_back_to_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $request = $this->requestFor(Lease::factory()->create());
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), [
            'assigned_to' => $admin->id, 'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => ''])
            ->assertSessionHas('status', 'Maintenance request unassigned.');

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->assigned_to);
        $this->assertNull($request->scheduled_date);
    }

    public function test_the_tenant_confirms_the_work_is_done(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $request = $this->requestFor(Lease::factory()->create());
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => $staff->id]);

        $this->actingAs($request->tenant)->get(route('tenant.maintenance-requests.index'))->assertOk()
            ->assertSee(route('tenant.maintenance-requests.resolve', $request))
            ->assertSee('Mark as resolved')
            // Cancelling is only for a request nobody has been assigned to.
            ->assertDontSee(route('tenant.maintenance-requests.cancel', $request));

        $this->actingAs($request->tenant)->post(route('tenant.maintenance-requests.resolve', $request))
            ->assertRedirect()->assertSessionHas('status');

        $request->refresh();
        $this->assertSame('resolved', $request->status);
        $this->assertNotNull($request->resolved_at);
        foreach ([$admin, $staff] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'title' => 'Maintenance request resolved',
                'message' => "{$request->tenant->name} confirmed the Plumbing issue in Room {$request->room->room_number} is fixed.",
            ]);
        }

        // Closed: nothing left to press, for the tenant or the admin.
        $this->actingAs($request->tenant)->get(route('tenant.maintenance-requests.index'))->assertDontSee('Mark as resolved');
        $this->actingAs($admin)->get(route('admin.maintenance-requests.index'))->assertSee('Confirmed by tenant')
            ->assertDontSee('name="assigned_to"', false);
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => $staff->id])->assertStatus(422);
        $this->actingAs($request->tenant)->post(route('tenant.maintenance-requests.resolve', $request))->assertStatus(422);
    }

    public function test_the_tenant_can_cancel_only_before_anyone_is_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = $this->requestFor(Lease::factory()->create());
        $assigned = $this->requestFor(Lease::factory()->create());
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $assigned), ['assigned_to' => $admin->id]);

        $this->actingAs($pending->tenant)->post(route('tenant.maintenance-requests.cancel', $pending))->assertRedirect();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'title' => 'Maintenance request cancelled']);

        $this->actingAs($assigned->tenant)->post(route('tenant.maintenance-requests.cancel', $assigned))->assertStatus(422);
        $this->assertSame('in_progress', $assigned->fresh()->status);
    }

    public function test_only_the_tenant_who_reported_it_can_close_it(): void
    {
        $request = $this->requestFor(Lease::factory()->create());
        $stranger = Lease::factory()->create()->tenant;

        $this->actingAs($stranger)->post(route('tenant.maintenance-requests.resolve', $request))->assertForbidden();
        $this->actingAs($stranger)->post(route('tenant.maintenance-requests.cancel', $request))->assertForbidden();
        // Admins assign; closing is the tenant's call (the tenant routes aren't theirs at all).
        $this->actingAs(User::factory()->admin()->create())->post(route('tenant.maintenance-requests.resolve', $request))->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_mobile_app_can_resolve_and_cancel(): void
    {
        $admin = User::factory()->admin()->create();
        $assigned = $this->requestFor(Lease::factory()->create());
        $this->actingAs($admin)->put(route('admin.maintenance-requests.update', $assigned), ['assigned_to' => $admin->id]);
        $pending = $this->requestFor(Lease::factory()->create());

        Sanctum::actingAs($assigned->tenant);
        $this->postJson("/api/maintenance-requests/{$assigned->id}/cancel")->assertStatus(422);
        $this->postJson("/api/maintenance-requests/{$assigned->id}/resolve")->assertOk()
            ->assertJsonPath('request.status', 'resolved')
            ->assertJsonPath('request.assigned_to.name', $admin->name);
        // Someone else's request.
        $this->postJson("/api/maintenance-requests/{$pending->id}/resolve")->assertForbidden();

        Sanctum::actingAs($pending->tenant);
        $this->postJson("/api/maintenance-requests/{$pending->id}/cancel")->assertOk()->assertJsonPath('request.status', 'cancelled');
    }

    public function test_a_tenant_cannot_use_the_admin_update_route(): void
    {
        $request = $this->requestFor(Lease::factory()->create());

        $this->actingAs($request->tenant)
            ->put(route('admin.maintenance-requests.update', $request), ['assigned_to' => $request->tenant_id])
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->assigned_to);
    }
}
