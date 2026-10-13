<?php

namespace App\Mail;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Heads-up for WAKATA staff when a customer writes in (only when WAKATA_SUPPORT_NOTIFY or WAKATA_ORDER_NOTIFY is set). */
class SupportMessageStaffMail extends Mailable
{
    public function __construct(public SupportThread $thread, public SupportMessage $message)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->thread->ref . '] ' . $this->thread->subject,
            replyTo: $this->thread->customer ? [$this->thread->customer->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-staff');
    }
}
