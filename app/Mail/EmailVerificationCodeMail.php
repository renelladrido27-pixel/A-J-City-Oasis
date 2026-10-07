<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable;

    public int $expiresMinutes = EmailVerificationService::EXPIRES_MINUTES;

    public function __construct(public User $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your A & J CITY OASIS verification code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.verification-code');
    }
}
