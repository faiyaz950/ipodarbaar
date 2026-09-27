<?php

namespace App\Jobs;

use App\Mail\IpoDigestMail;
use App\Models\Subscriber;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one digest email. Rate limited so a large list stays under the hosting
 * provider's hourly SMTP cap; throttled jobs wait in the queue and go out later.
 */
class SendDigestMail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(public int $subscriberId, public array $digest) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('digest-mail')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(20);
    }

    public function handle(): void
    {
        $subscriber = Subscriber::find($this->subscriberId);

        if (! $subscriber || ! $subscriber->isReceiving()) {
            return;
        }

        Mail::to($subscriber->email)->send(new IpoDigestMail($this->digest, $subscriber->unsubscribeUrl()));

        $subscriber->forceFill(['last_sent_at' => now()])->save();
    }
}
