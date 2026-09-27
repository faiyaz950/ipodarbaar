<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The daily/weekly IPO digest. Built from IpoDigestBuilder::email() data.
 */
class IpoDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(public array $digest, public string $unsubscribeUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->digest['subject'],
        );
    }

    /**
     * One-click unsubscribe support for Gmail / Yahoo (RFC 8058).
     */
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ipo-digest',
        );
    }
}
