<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guards that matter once the app is on the public internet.
 */
class ProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_is_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['intent' => 'signin', 'email' => $user->email, 'password' => 'wrong'])
                ->assertRedirect();
        }

        $this->post('/login', ['intent' => 'signin', 'email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_mobile_login_is_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 't'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 't'])
            ->assertStatus(429);
    }

    public function test_the_limit_is_per_account_so_one_attacked_account_does_not_lock_out_others(): void
    {
        $attacked = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['intent' => 'signin', 'email' => $attacked->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['intent' => 'signin', 'email' => $other->email, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($other);
    }

    public function test_demo_accounts_with_the_public_password_are_not_seeded_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Called directly: `db:seed` itself stops to ask for confirmation in production.
        (new AdminUserSeeder)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_accounts_are_still_seeded_locally(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@ajoasis.test', 'role' => 'admin']);
    }

    /**
     * Shared hosting (Hostinger) disables proc_open(), which Schedule::command()
     * needs to launch a command as a separate process — the daily jobs would
     * silently never run. They must be in-process callbacks instead.
     */
    public function test_scheduled_jobs_run_in_process_so_they_work_on_shared_hosting(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $this->assertEqualsCanonicalizing(
            ['expire-stale-bookings', 'check-overdue-payments', 'scheduler-heartbeat'],
            $events->pluck('description')->all(),
        );
        $events->each(fn ($event) => $this->assertInstanceOf(\Illuminate\Console\Scheduling\CallbackEvent::class, $event));
    }

    public function test_the_scheduler_actually_runs_the_daily_jobs(): void
    {
        $stale = \App\Models\Booking::factory()->create(['move_in_deadline' => now()->subDay()]);

        $this->travelTo(now()->addDay()->startOfDay());
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('scheduler:heartbeat'));
    }

    public function test_create_admin_command_creates_a_hashed_admin_account(): void
    {
        $this->artisan('app:create-admin', ['email' => 'Owner@AJOasis.com', '--first-name' => 'Jose', '--last-name' => 'Valle'])
            ->expectsQuestion('Password (min. 8 characters, with upper/lower case, a number and a symbol)', 'S3cure-pass!')
            ->expectsQuestion('Confirm password', 'S3cure-pass!')
            ->assertSuccessful();

        $admin = User::where('email', 'owner@ajoasis.com')->sole();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('S3cure-pass!', $admin->password));
    }

    public function test_create_admin_rejects_short_or_mismatched_passwords(): void
    {
        $this->artisan('app:create-admin', ['email' => 'a@b.com', '--first-name' => 'A', '--last-name' => 'B'])
            ->expectsQuestion('Password (min. 8 characters, with upper/lower case, a number and a symbol)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->artisan('app:create-admin', ['email' => 'a@b.com', '--first-name' => 'A', '--last-name' => 'B'])
            ->expectsQuestion('Password (min. 8 characters, with upper/lower case, a number and a symbol)', 'Long-enough-1')
            ->expectsQuestion('Confirm password', 'Different-2')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_create_admin_will_not_promote_a_tenant_account(): void
    {
        $tenant = User::factory()->create();

        $this->artisan('app:create-admin', ['email' => $tenant->email, '--first-name' => 'X', '--last-name' => 'Y'])->assertFailed();

        $this->assertSame('tenant', $tenant->fresh()->role);
    }
}
