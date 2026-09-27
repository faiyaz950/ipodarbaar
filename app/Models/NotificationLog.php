<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Remembers which one-off messages (Telegram posts, email digests) were already sent.
 */
class NotificationLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['channel', 'key', 'sent_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Atomically reserve a message. Returns false when it was already sent (or is being sent).
     */
    public static function claim(string $channel, string $key): bool
    {
        return static::query()->insertOrIgnore([
            'channel' => $channel,
            'key' => $key,
            'sent_at' => now(),
        ]) === 1;
    }

    /**
     * Give a reservation back after a failed send so the next run retries it.
     */
    public static function release(string $channel, string $key): void
    {
        static::query()->where('channel', $channel)->where('key', $key)->delete();
    }

    public static function wasSent(string $channel, string $key): bool
    {
        return static::query()->where('channel', $channel)->where('key', $key)->exists();
    }
}
