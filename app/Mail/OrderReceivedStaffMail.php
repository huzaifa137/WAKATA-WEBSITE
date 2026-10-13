<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Heads-up for WAKATA staff (only when WAKATA_ORDER_NOTIFY is set). */
class OrderReceivedStaffMail extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New order ' . $this->order->number . ' (UGX ' . number_format($this->order->total) . ')');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-placed', with: ['staff' => true]);
    }
}
