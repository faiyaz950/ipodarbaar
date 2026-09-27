<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously from Admin → System so the admin sees immediately whether SMTP works.
 */
class SystemTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'IPO Darbaar: test email',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.system-test',
            with: ['sentAt' => now()],
        );
    }
}
