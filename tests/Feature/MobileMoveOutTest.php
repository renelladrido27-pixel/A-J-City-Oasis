<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Moving out from the app — the same request the website's "Request
 * Move-Out" makes, with the same rules.
 */
class MobileMoveOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_can_ask_to_move_out_from_the_app(): void
    {
        $this->travelTo('2026-10-25 09:00:00');
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create(['start_date' => '2026-10-01']);
        Sanctum::actingAs($lease->tenant);

        $this->getJson('/api/lease')->assertOk()->assertJsonPath('lease.move_out', null);

        $this->postJson("/api/leases/{$lease->id}/move-out", [
            'requested_move_out_date' => '2026-11-15', 'reason' => 'Moving back home',
        ])->assertCreated()
            ->assertJsonPath('lease.move_out.requested_date', '2026-11-15')
            ->assertJsonPath('lease.move_out.status', 'awaiting_inspection')
            ->assertJsonPath('lease.move_out.reason', 'Moving back home');

        $this->getJson('/api/lease')->assertJsonPath('lease.move_out.status', 'awaiting_inspection');
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'title' => 'New move-out request']);

        // Only once per lease — same as the website.
        $this->postJson("/api/leases/{$lease->id}/move-out", ['requested_move_out_date' => '2026-11-20'])
            ->assertStatus(422)->assertJsonPath('message', 'A move-out has already been requested for this lease.');
        $this->actingAs($lease->tenant)->get(route('tenant.leases.show', $lease))->assertOk()
            ->assertDontSee(route('tenant.move-outs.create', $lease));
    }

    public function test_the_date_cannot_be_in_the_past_and_only_the_tenant_can_ask(): void
    {
        $this->travelTo('2026-10-25 09:00:00');
        $lease = Lease::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/leases/{$lease->id}/move-out", ['requested_move_out_date' => '2026-11-15'])->assertForbidden();

        Sanctum::actingAs($lease->tenant);
        $this->postJson("/api/leases/{$lease->id}/move-out", ['requested_move_out_date' => '2026-10-20'])
            ->assertStatus(422)->assertJsonValidationErrors('requested_move_out_date');
        $this->assertNull($lease->fresh()->moveOut);
    }

    public function test_the_website_request_still_works_the_same_way(): void
    {
        $this->travelTo('2026-10-25 09:00:00');
        $lease = Lease::factory()->create();

        $this->actingAs($lease->tenant)->get(route('tenant.leases.show', $lease))->assertSee('Request Move-Out');
        $this->actingAs($lease->tenant)->post(route('tenant.move-outs.store', $lease), ['requested_move_out_date' => '2026-11-15'])
            ->assertRedirect(route('tenant.leases.show', $lease))
            ->assertSessionHas('status');
        $this->assertSame('2026-11-15', $lease->fresh()->moveOut->requested_move_out_date->toDateString());
    }
}
