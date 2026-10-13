<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Email me this file" from the library: the PDF is attached. */
class LibraryFileMail extends Mailable
{
    public function __construct(public string $name, public string $title, public string $filename, public string $bytes, public int $left)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title . ' (PDF from WAKATA)');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.library-file');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->bytes, $this->filename)->withMime('application/pdf')];
    }
}
