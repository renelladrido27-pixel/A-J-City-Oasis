<?php

namespace App\Mail;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaseStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lease $lease) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to A&J CITY OASIS — your lease has started',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lease-started',
        );
    }
}
