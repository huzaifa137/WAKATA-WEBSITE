<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the customer as soon as the order is placed. */
class OrderPlacedMail extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your WAKATA order ' . $this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-placed', with: ['staff' => false]);
    }
}
