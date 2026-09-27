<?php

namespace App\Mail;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Double opt-in: the digest starts only after this link is opened.
 */
class SubscriptionConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Subscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your IPO Darbaar digest',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.subscription-confirm',
            with: [
                'confirmUrl' => $this->subscriber->confirmUrl(),
                'frequency' => Subscriber::FREQUENCIES[$this->subscriber->frequency] ?? $this->subscriber->frequency,
                'hours' => Subscriber::CONFIRM_HOURS,
            ],
        );
    }
}
