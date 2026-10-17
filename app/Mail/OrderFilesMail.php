<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Staff pressed "Resend" on an order with PDF files: the files are attached. It does not use up the customer's downloads. */
class OrderFilesMail extends Mailable
{
    /** @param array<string, string> $files file name => file bytes */
    public function __construct(public Order $order, public array $files)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your PDF files from WAKATA (' . $this->order->number . ')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-files');
    }

    public function attachments(): array
    {
        $out = [];
        foreach ($this->files as $name => $bytes) {
            $out[] = Attachment::fromData(fn () => $bytes, $name)->withMime('application/pdf');
        }

        return $out;
    }
}
