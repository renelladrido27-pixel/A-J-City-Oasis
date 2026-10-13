<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The app can show a receipt for anything the tenant has paid — the same
 * details as the website's printable receipt — and the photo of a utility bill.
 */
class MobileReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_payment_has_a_receipt_with_the_same_details_as_the_website(): void
    {
        $this->travelTo('2026-10-02 15:15:00');
        $payment = Payment::factory()->create([
            'status' => 'paid', 'amount' => 3500, 'paid_at' => now(), 'xendit_payment_id' => 'pay-abc-123',
        ]);
        $tenant = $payment->lease->tenant;
        Sanctum::actingAs($tenant);

        $this->getJson("/api/payments/{$payment->id}/receipt")->assertOk()->assertExactJson(['receipt' => [
            'number' => str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'business' => 'A&J CITY OASIS',
            'business_address' => 'Koronadal City, South Cotabato',
            'billed_to' => ['name' => $tenant->name, 'email' => $tenant->email],
            'room' => ['number' => $payment->lease->room->room_number, 'property' => $payment->lease->room->property->name],
            'description' => 'Rent',
            'amount' => 3500,
            'paid_at' => now()->toIso8601String(),
            'reference' => 'pay-abc-123',
            'bill_photo_url' => null,
        ]]);

        // Everything on it is also on the website's receipt page.
        $this->actingAs($tenant)->get(route('payments.receipt', $payment))->assertOk()
            ->assertSee('#'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('pay-abc-123')
            ->assertSee('₱3,500.00');
    }

    public function test_a_walk_in_payment_recorded_by_the_admin_says_so(): void
    {
        $payment = Payment::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.payments.record-manual', $payment));
        Sanctum::actingAs($payment->lease->tenant);

        $this->getJson("/api/payments/{$payment->id}/receipt")->assertOk()
            ->assertJsonPath('receipt.reference', 'Recorded manually');
    }

    public function test_there_is_no_receipt_for_an_unpaid_bill_or_someone_elses_payment(): void
    {
        $unpaid = Payment::factory()->create();
        $othersPaid = Payment::factory()->create(['status' => 'paid', 'paid_at' => now()]);
        Sanctum::actingAs($unpaid->lease->tenant);

        $this->getJson("/api/payments/{$unpaid->id}/receipt")->assertNotFound();
        $this->getJson("/api/payments/{$othersPaid->id}/receipt")->assertForbidden();
    }

    public function test_the_paid_upfront_booking_payment_is_in_the_apps_history_with_a_receipt(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $upfront = Payment::create([
            'booking_id' => $booking->id, 'type' => 'booking_upfront', 'amount' => 10500,
            'due_date' => now(), 'status' => 'paid', 'paid_at' => now(),
        ]);
        // Someone else's paid booking, and this tenant's own unpaid one, stay out.
        $othersBooking = Booking::factory()->confirmed()->create();
        Payment::create(['booking_id' => $othersBooking->id, 'type' => 'booking_upfront', 'amount' => 10500, 'due_date' => now(), 'status' => 'paid', 'paid_at' => now()]);
        $pendingBooking = Booking::factory()->create(['user_id' => $booking->user_id]);
        Payment::create(['booking_id' => $pendingBooking->id, 'type' => 'booking_upfront', 'amount' => 10500, 'due_date' => now(), 'status' => 'pending']);
        Sanctum::actingAs($booking->tenant);

        $this->getJson('/api/payments')->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.id', $upfront->id)
            ->assertJsonPath('history.0.type', 'booking_upfront')
            // Not counted as something still owed.
            ->assertJsonPath('outstanding_balance', 0)
            ->assertJsonPath('next_due_payment_id', null);

        $this->getJson("/api/payments/{$upfront->id}/receipt")->assertOk()
            ->assertJsonPath('receipt.description', 'Booking upfront')
            ->assertJsonPath('receipt.amount', 10500)
            ->assertJsonPath('receipt.room.number', $booking->room->room_number);
    }

    public function test_a_utility_bill_carries_the_photo_the_admin_attached(): void
    {
        $lease = Lease::factory()->create();
        $payment = Payment::factory()->create(['lease_id' => $lease->id, 'type' => 'utility', 'amount' => 820, 'status' => 'paid', 'paid_at' => now()]);
        UtilityBill::create([
            'lease_id' => $lease->id, 'payment_id' => $payment->id, 'type' => 'water', 'amount' => 820,
            'due_date' => $payment->due_date, 'status' => 'paid', 'receipt_photo' => 'utility-receipts/water.jpg',
            'encoded_by' => User::factory()->admin()->create()->id,
        ]);
        $rent = Payment::factory()->create(['lease_id' => $lease->id]);
        Sanctum::actingAs($lease->tenant);

        $history = collect($this->getJson('/api/payments')->assertOk()->json('history'))->keyBy('id');
        $this->assertSame(asset('storage/utility-receipts/water.jpg'), $history[$payment->id]['bill_photo_url']);
        $this->assertNull($history[$rent->id]['bill_photo_url']);

        $this->getJson("/api/payments/{$payment->id}/receipt")->assertOk()
            ->assertJsonPath('receipt.bill_photo_url', asset('storage/utility-receipts/water.jpg'));
    }
}
