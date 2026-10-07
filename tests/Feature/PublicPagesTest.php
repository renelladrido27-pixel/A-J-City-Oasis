<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_the_site_and_see_vacant_rooms(): void
    {
        $vacant = Room::factory()->create(['room_number' => '101']);
        Room::factory()->create(['room_number' => '102', 'status' => 'occupied']);

        $this->get('/')->assertOk()->assertSee('A &amp; J CITY OASIS', false);
        $this->get(route('rooms.browse'))->assertOk()->assertSee('101');
        $this->get(route('rooms.show', $vacant))->assertOk()->assertSee('101');
        $this->get(route('login'))->assertOk();
    }

    public function test_tenant_pages_require_login(): void
    {
        $this->get(route('tenant.dashboard'))->assertRedirect(route('login'));
        $this->get(route('tenant.payments.index'))->assertRedirect(route('login'));
    }

    public function test_tenants_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_each_role_lands_on_its_own_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('tenant.dashboard'))->assertOk();
    }
}
