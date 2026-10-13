<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website's receipt can be saved as a real PDF file.
 */
class ReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    private function paidRent(): Payment
    {
        return Payment::factory()->create([
            'status' => 'paid', 'amount' => 3500, 'paid_at' => '2026-10-02 15:15:00', 'xendit_payment_id' => 'pay-abc-123',
        ]);
    }

    public function test_the_receipt_page_offers_save_as_pdf_next_to_print(): void
    {
        $payment = $this->paidRent();

        $this->actingAs($payment->lease->tenant)->get(route('payments.receipt', $payment))->assertOk()
            ->assertSee(route('payments.receipt.pdf', $payment))
            ->assertSee('Save as PDF')
            ->assertSee('Print');
    }

    public function test_save_as_pdf_downloads_a_pdf_file_named_after_the_receipt(): void
    {
        $payment = $this->paidRent();
        $number = str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($payment->lease->tenant)->get(route('payments.receipt.pdf', $payment));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="AJ-City-Oasis-receipt-'.$number.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    public function test_the_pdf_carries_the_same_details_as_the_receipt_page(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'advance_amount' => 3500, 'deposit_amount' => 3500, 'security_amount' => 3500, 'total_amount' => 10500,
        ]);
        $upfront = Payment::create([
            'booking_id' => $booking->id, 'type' => 'booking_upfront', 'amount' => 10500,
            'due_date' => now(), 'status' => 'paid', 'paid_at' => '2026-10-02 15:15:00', 'xendit_payment_id' => 'pay-xyz-789',
        ]);
        $upfront->load(['booking.room.property', 'booking.tenant']);

        // What the PDF is rendered from.
        $html = view('payments.receipt-pdf', ['payment' => $upfront])->render();

        foreach ([
            'Receipt', '#'.str_pad((string) $upfront->id, 6, '0', STR_PAD_LEFT),
            e($booking->tenant->name), $booking->tenant->email, $booking->room->room_number,
            '1 month advance', '1 month deposit', 'Security deposit', '₱3,500.00', '₱10,500.00',
            'Oct 02, 2026 3:15 PM', 'pay-xyz-789', 'Paid in Full',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $this->actingAs($booking->tenant)->get(route('payments.receipt.pdf', $upfront))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_only_the_payer_or_an_admin_can_download_it_and_only_once_paid(): void
    {
        $payment = $this->paidRent();
        $unpaid = Payment::factory()->create();

        $this->get(route('payments.receipt.pdf', $payment))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('payments.receipt.pdf', $payment))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('payments.receipt.pdf', $payment))->assertOk();
        $this->actingAs($unpaid->lease->tenant)->get(route('payments.receipt.pdf', $unpaid))->assertNotFound();
    }
}
