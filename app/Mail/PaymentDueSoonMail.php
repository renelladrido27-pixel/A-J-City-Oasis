<?php

namespace App\Mail;

use App\Console\Commands\SendPaymentReminders;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentDueSoonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: payment due '.SendPaymentReminders::when($this->payment),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-due-soon',
            with: ['when' => SendPaymentReminders::when($this->payment)],
        );
    }
}
