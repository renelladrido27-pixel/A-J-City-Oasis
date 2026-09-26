<?php

namespace App\Mail;

use App\Models\UtilityBill;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UtilityBillEncodedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UtilityBill $utilityBill) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.ucfirst($this->utilityBill->type).' bill',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.utility-bill-encoded',
        );
    }
}
