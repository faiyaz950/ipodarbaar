<?php

namespace App\Models;

use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/**
 * An email address that opted in (double opt-in) to the IPO digest.
 */
class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    public const FREQUENCIES = [
        'daily' => 'Daily (8 AM)',
        'weekly' => 'Weekly (Monday 8 AM)',
    ];

    /** Confirmation links stay valid for this many hours. */
    public const CONFIRM_HOURS = 48;

    protected $fillable = ['email', 'frequency'];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function scopeReceiving(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function isReceiving(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }

    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute('subscribe.confirm', now()->addHours(self::CONFIRM_HOURS), $this);
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscribe', $this);
    }

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
