<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationCodeMail;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Support\AccountRules;
use App\Support\Floor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Changes requested by the defense panel: separate name fields, phone and
 * password rules, account verification, floor names, a clearer upfront
 * payment, who handles a maintenance request, and "resolved" status.
 */
class PanelFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function signUpData(array $overrides = []): array
    {
        return array_merge([
            'intent' => 'signup',
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'email' => 'maria@example.com',
            'phone' => '09171234567',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ], $overrides);
    }

    /** Signs up through the website and returns the 6-digit code that was emailed. */
    private function signUpAndGetCode(array $overrides = []): string
    {
        Mail::fake();
        $this->post('/register', $this->signUpData($overrides))->assertRedirect(route('verification.notice'));

        $code = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    // ── 1. First name / middle name / surname ───────────────────────────────

    public function test_sign_up_stores_first_middle_and_surname_separately(): void
    {
        $this->signUpAndGetCode();

        $user = User::where('email', 'maria@example.com')->sole();
        $this->assertSame('Maria', $user->first_name);
        $this->assertSame('Santos', $user->middle_name);
        $this->assertSame('Dela Cruz', $user->last_name);
        // The combined name used across the app stays in sync.
        $this->assertSame('Maria Santos Dela Cruz', $user->name);
    }

    public function test_first_name_and_surname_are_required_and_middle_name_is_optional(): void
    {
        $this->post('/register', $this->signUpData(['first_name' => '', 'last_name' => '']))
            ->assertSessionHasErrors(['first_name', 'last_name']);

        Mail::fake();
        $this->post('/register', $this->signUpData(['middle_name' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Maria Dela Cruz', User::sole()->name);
    }

    public function test_names_cannot_contain_numbers_or_symbols(): void
    {
        $this->post('/register', $this->signUpData(['first_name' => 'Maria123']))->assertSessionHasErrors('first_name');
        $this->post('/register', $this->signUpData(['last_name' => 'Cruz@#']))->assertSessionHasErrors('last_name');

        Mail::fake();
        $this->post('/register', $this->signUpData(['first_name' => 'María-José', 'last_name' => "O'Niño Jr."]))->assertSessionHasNoErrors();
    }

    public function test_changing_the_name_parts_updates_the_display_name(): void
    {
        $user = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Reyes']);
        $this->assertSame('Juan Reyes', $user->name);

        $user->update(['middle_name' => 'Garcia']);
        $this->assertSame('Juan Garcia Reyes', $user->fresh()->name);
    }

    // ── 2. Phone number validation ──────────────────────────────────────────

    public function test_phone_must_be_a_philippine_mobile_number(): void
    {
        foreach (['12345', '0917123456', '091712345678', '0831234567', 'abcdefghijk', '+15551234567'] as $bad) {
            $this->post('/register', $this->signUpData(['phone' => $bad]))->assertSessionHasErrors('phone');
        }
        $this->post('/register', $this->signUpData(['phone' => '']))->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_phone_formats_are_accepted_and_stored_in_one_format(): void
    {
        $this->assertSame('09171234567', AccountRules::normalizePhone('+63 917 123 4567'));
        $this->assertSame('09171234567', AccountRules::normalizePhone('0917-123-4567'));
        $this->assertNull(AccountRules::normalizePhone('  '));

        $this->signUpAndGetCode(['phone' => '+63 917 123 4567']);
        $this->assertSame('09171234567', User::sole()->phone);
    }

    // ── 3. Password strength ────────────────────────────────────────────────

    public function test_password_needs_mixed_case_a_number_and_a_symbol(): void
    {
        foreach (['password', 'Password', 'Password123', 'password123!', 'PASSWORD123!', 'Pw1!'] as $weak) {
            $this->post('/register', $this->signUpData(['password' => $weak, 'password_confirmation' => $weak]))
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_sign_up_form_shows_the_password_rules(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('At least 8 characters')
            ->assertSee('A symbol')
            ->assertSee('First name')
            ->assertSee('Surname');
    }

    // ── 6. Account verification by emailed code ─────────────────────────────

    public function test_a_new_account_is_unverified_and_cannot_use_the_tenant_portal(): void
    {
        $this->signUpAndGetCode();
        $user = User::sole();

        $this->assertFalse($user->hasVerifiedEmail());
        $this->actingAs($user)->get(route('tenant.dashboard'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('tenant.bookings.store', Room::factory()->create()), ['agreed_to_terms' => '1'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('bookings', 0);

        // The code screen and the profile (to fix a mistyped email) stay reachable.
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('maria@example.com');
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_entering_the_emailed_code_verifies_the_account(): void
    {
        $code = $this->signUpAndGetCode();
        $user = User::sole();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $user->email_verification_code, 'the code is stored hashed');

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('tenant.dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->email_verification_code);
        $this->actingAs($user->fresh())->get(route('tenant.dashboard'))->assertOk();
    }

    public function test_a_wrong_or_expired_code_is_rejected(): void
    {
        $code = $this->signUpAndGetCode();
        $user = User::sole();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)->post(route('verification.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->actingAs($user)->post(route('verification.verify'), ['code' => 'abc'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->travel(16)->minutes();
        $this->actingAs($user)->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_new_code_can_be_requested(): void
    {
        $first = $this->signUpAndGetCode();
        $user = User::sole();
        $hashBefore = $user->email_verification_code;

        Mail::fake();
        $this->actingAs($user)->post(route('verification.resend'))->assertRedirect();

        Mail::assertSent(EmailVerificationCodeMail::class, 1);
        $this->assertNotSame($hashBefore, $user->fresh()->email_verification_code);
    }

    public function test_code_guessing_is_rate_limited(): void
    {
        $this->signUpAndGetCode();
        $user = User::sole();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('verification.verify'), ['code' => '000001']);
        }

        $this->actingAs($user)->post(route('verification.verify'), ['code' => '000001'])->assertStatus(429);
    }

    public function test_accounts_created_by_the_admin_are_already_verified(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.users.store'), [
            'first_name' => 'Pedro', 'last_name' => 'Ramos', 'email' => 'pedro@example.com', 'phone' => '09181234567',
            'role' => 'tenant', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue(User::where('email', 'pedro@example.com')->sole()->hasVerifiedEmail());
    }

    public function test_changing_email_requires_verifying_the_new_address(): void
    {
        Mail::fake();
        $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Lim', 'phone' => '09171234567']);

        $this->actingAs($user)->put(route('profile.update'), [
            'first_name' => 'Ana', 'last_name' => 'Lim', 'email' => 'new@example.com', 'phone' => '09171234567',
        ])->assertRedirect(route('verification.notice'));

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Mail::assertSent(EmailVerificationCodeMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    public function test_the_mobile_app_must_verify_before_booking(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        // The app's "create account & book" form: the account is created, but the room is not booked yet.
        $response = $this->postJson("/api/rooms/{$room->id}/book", [
            'first_name' => 'Maria', 'last_name' => 'Dela Cruz', 'email' => 'maria@example.com', 'phone' => '09171234567',
            'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!', 'move_in_date' => now()->addDays(2)->toDateString(),
        ])->assertCreated()->assertJson(['verification_required' => true, 'user' => ['email_verified' => false, 'first_name' => 'Maria']]);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame('vacant', $room->fresh()->status);

        $code = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $user = User::sole();
        Sanctum::actingAs($user);

        $this->getJson('/api/lease')->assertStatus(403)->assertJson(['code' => 'email_unverified']);
        $this->postJson("/api/rooms/{$room->id}/book", ['move_in_date' => now()->addDays(2)->toDateString()])
            ->assertStatus(403)->assertJson(['code' => 'email_unverified']);

        $this->postJson('/api/email/verify', ['code' => '999999'])->assertStatus(422);
        $this->postJson('/api/email/verify', ['code' => $code])->assertOk()->assertJson(['verified' => true]);

        Sanctum::actingAs($user->fresh());
        $this->postJson("/api/rooms/{$room->id}/book", ['move_in_date' => now()->addDays(2)->toDateString()])->assertCreated();
        $this->assertSame('reserved', $room->fresh()->status);
    }

    // ── 4. Floor names ──────────────────────────────────────────────────────

    public function test_floors_are_shown_in_words(): void
    {
        $this->assertSame('First Floor', Floor::label(1));
        $this->assertSame('Second Floor', Floor::label(2));
        $this->assertSame('Third Floor', Floor::label('3'));
        $this->assertSame('21st Floor', Floor::label(21));
        $this->assertSame('—', Floor::label(null));

        $room = Room::factory()->create(['floor' => 2]);

        $this->get(route('rooms.show', $room))->assertOk()->assertSee('Second Floor')->assertDontSee('Floor 2');
        $this->get(route('rooms.browse'))->assertOk()->assertSee('Second Floor');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.rooms.index'))->assertOk()->assertSee('Second Floor');
    }

    public function test_floor_announcements_name_the_floor_in_words(): void
    {
        $property = Property::factory()->create(['name' => 'Main Building']);
        $announcement = Announcement::factory()->create(['audience' => 'floor', 'property_id' => $property->id, 'floor' => 3]);

        $this->assertSame('Third Floor — Main Building', $announcement->targetDescription());
    }

    // ── 5 & 9. The ₱10,500 upfront total and the ₱3,500 monthly rent ────────

    public function test_the_room_page_emphasises_the_upfront_total_and_the_monthly_rent(): void
    {
        $room = Room::factory()->create(['monthly_rate' => 3500]);

        $this->get(route('rooms.show', $room))->assertOk()
            ->assertSeeInOrder(['Pay today to book', '₱10,500.00', '1 month advance', '1 month deposit', 'Security deposit', 'Then, monthly rent', '₱3,500.00']);
        $this->get(route('rooms.browse'))->assertOk()->assertSee('₱10,500.00 to book');
    }

    public function test_the_booking_page_repeats_the_total_on_the_pay_button(): void
    {
        $room = Room::factory()->create(['monthly_rate' => 3500]);

        $this->actingAs(User::factory()->create())->get(route('tenant.bookings.create', $room))->assertOk()
            ->assertSee('Pay today to book')
            ->assertSee('Confirm &amp; Pay ₱10,500.00', false);
    }

    // ── 7. Who will do the maintenance ──────────────────────────────────────

    private function maintenanceRequest(): MaintenanceRequest
    {
        $lease = Lease::factory()->create();

        return MaintenanceRequest::create([
            'lease_id' => $lease->id, 'tenant_id' => $lease->tenant_id, 'room_id' => $lease->room_id,
            'category' => 'Plumbing', 'description' => 'Leaking sink', 'status' => 'pending',
        ]);
    }

    public function test_the_tenant_sees_who_is_assigned_and_when(): void
    {
        $request = $this->maintenanceRequest();
        $staff = User::factory()->staff()->create(['name' => 'Ramon Cruz', 'phone' => '09181112222']);

        $this->actingAs($request->tenant)->get(route('tenant.maintenance-requests.index'))->assertOk()->assertSee('Not yet assigned');

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.maintenance-requests.update', $request), [
            'assigned_to' => $staff->id, 'scheduled_date' => '2026-10-12',
        ])->assertRedirect();

        $this->actingAs($request->tenant)->get(route('tenant.maintenance-requests.index'))->assertOk()
            ->assertSee('Assigned To')->assertSee('Ramon Cruz')->assertSee('09181112222')->assertSee('Oct 12, 2026');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $request->tenant_id,
            'message' => 'Your Plumbing request is now in progress. Assigned to Ramon Cruz, scheduled for Oct 12, 2026. Once the work is done, please mark it as resolved.',
        ]);

        Sanctum::actingAs($request->tenant);
        $this->getJson('/api/maintenance-requests')->assertOk()->assertJsonPath('requests.0.assigned_to.name', 'Ramon Cruz')
            ->assertJsonPath('requests.0.scheduled_date', '2026-10-12');
    }

    public function test_the_assigned_staff_member_is_notified(): void
    {
        $request = $this->maintenanceRequest();
        $staff = User::factory()->staff()->create();

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.maintenance-requests.update', $request), [
            'assigned_to' => $staff->id,
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $staff->id, 'title' => 'Maintenance job assigned to you']);
    }

    public function test_a_request_can_only_be_assigned_to_staff_or_admin(): void
    {
        $request = $this->maintenanceRequest();

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.maintenance-requests.update', $request), [
            'assigned_to' => User::factory()->create()->id,
        ])->assertSessionHasErrors('assigned_to');
    }

    // ── 8. Completed → Resolved ─────────────────────────────────────────────

    public function test_finished_requests_are_resolved_not_completed(): void
    {
        $request = $this->maintenanceRequest();
        $admin = User::factory()->admin()->create();

        $this->assertContains('resolved', MaintenanceRequest::STATUSES);
        $this->assertNotContains('completed', MaintenanceRequest::STATUSES);

        // The tenant is the one who confirms the work is done.
        $this->actingAs($request->tenant)->post(route('tenant.maintenance-requests.resolve', $request))->assertRedirect();
        $this->assertSame('resolved', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->resolved_at);

        $this->actingAs($admin)->get(route('admin.maintenance-requests.index'))->assertOk()->assertSee('Resolved')->assertDontSee('Completed');
    }
}
