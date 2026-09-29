<?php

namespace App\Mail;

use App\Models\MoveOut;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MoveOutSettledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MoveOut $moveOut) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Move-out settlement — Room '.$this->moveOut->lease->room->room_number);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.move-out-settled');
    }
}
