<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the customer when staff change an order's status ("Email the customer about this change") or press "Email customer". */
class OrderUpdateMail extends Mailable
{
    public function __construct(public Order $order, public string $headline, public string $body)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline . ' (' . $this->order->number . ')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-update');
    }
}
