<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — '.ucfirst(str_replace('_', ' ', $this->payment->type)),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-receipt',
        );
    }
}
