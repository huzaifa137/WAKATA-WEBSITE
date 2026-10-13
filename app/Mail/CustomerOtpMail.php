<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The two-step sign-in code for customers (also used to prove who owns a guest order: see ClaimOrderController). */
class CustomerOtpMail extends Mailable
{
    public function __construct(public string $name, public string $code, public string $purpose = 'sign-in') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->purpose === 'order' ? 'Your WAKATA order verification code' : 'Your WAKATA sign-in code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-otp');
    }
}
