<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable
{
    public function __construct(public string $name, public string $url, public string $area = 'WAKATA') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your ' . $this->area . ' password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reset-password');
    }
}
