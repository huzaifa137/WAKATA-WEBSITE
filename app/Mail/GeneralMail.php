<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One plain, branded email for everything staff send by hand: an answer to a message, a quotation, a newsletter, an alert.
 *
 * @param  array<int, array{0:string,1:string,2:string,3:string}>|null  $rows  quotation lines: item, qty, unit price, line total
 */
class GeneralMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?string $heading = null,
        public ?array $rows = null,
        public ?array $totals = null,
        public ?string $footerNote = null,
        public ?string $unsubscribeUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.general');
    }
}
