<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deactivating an account takes effect at once — on the website and in the
 * app — not only the next time the person tries to log in.
 */
class AccountDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private function deactivate(User $user): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.toggle-active', $user))
            ->assertSessionHas('status', 'Account deactivated.');
        $this->assertFalse($user->fresh()->is_active);
        $this->app['auth']->forgetGuards();
    }

    public function test_a_tenant_signed_in_on_the_app_is_signed_out_when_deactivated(): void
    {
        $tenant = User::factory()->create();
        $token = $tenant->createToken('mobile')->plainTextToken;
        $app = fn () => $this->withToken($token)->getJson('/api/user');

        $app()->assertOk();

        $this->deactivate($tenant);

        $app()->assertForbidden()
            ->assertJsonPath('code', 'account_deactivated')
            ->assertJsonPath('message', 'This account has been deactivated. Please contact the A&J CITY OASIS office.');
        // The token is revoked, so it stays dead even if the account is turned back on.
        $this->assertSame(0, $tenant->tokens()->count());

        // Logging in again is refused too.
        $this->postJson('/api/login', ['email' => $tenant->email, 'password' => 'password'])->assertStatus(422);
    }

    public function test_every_app_screen_is_refused_including_booking(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create();

        $this->deactivate($tenant);

        foreach (['/api/lease', '/api/payments', '/api/maintenance-requests', '/api/notifications', '/api/profile'] as $path) {
            $token = $tenant->createToken('mobile')->plainTextToken;
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->getJson($path)->assertForbidden()->assertJsonPath('code', 'account_deactivated');
        }

        $token = $tenant->createToken('mobile')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/rooms/{$room->id}/book", ['agreed_to_terms' => true])
            ->assertForbidden()->assertJsonPath('code', 'account_deactivated');
        $this->assertSame(0, $room->bookings()->count());
    }

    public function test_a_tenant_signed_in_on_the_website_is_signed_out_when_deactivated(): void
    {
        $tenant = User::factory()->create();
        $this->actingAs($tenant)->get(route('tenant.dashboard'))->assertOk();

        $this->deactivate($tenant);

        // (fresh(): a real session reloads the account from the database on every page.)
        $this->actingAs($tenant->fresh())->get(route('tenant.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_are_signed_out_too_and_reactivation_lets_them_back_in(): void
    {
        $staff = User::factory()->staff()->create();

        $this->deactivate($staff);
        $this->actingAs($staff->fresh())->get(route('staff.maintenance.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.users.toggle-active', $staff));
        $this->app['auth']->forgetGuards();
        $this->post('/logout');
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($staff->fresh());
    }
}
