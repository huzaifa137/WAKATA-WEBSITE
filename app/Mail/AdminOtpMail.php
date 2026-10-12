<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminOtpMail extends Mailable
{
    public function __construct(public string $name, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WAKATA Admin sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-otp');
    }
}
