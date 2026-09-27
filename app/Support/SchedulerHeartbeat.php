<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The scheduler records a beat every minute. Other code can then tell whether cron is
 * running (for example, to skip syncing IPO data during page requests).
 */
class SchedulerHeartbeat
{
    public const KEY = 'scheduler:heartbeat';

    public static function beat(): void
    {
        Cache::forever(self::KEY, now()->toIso8601String());
    }

    public static function lastBeatAt(): ?Carbon
    {
        $value = Cache::get(self::KEY);

        return $value ? Carbon::parse($value) : null;
    }

    public static function isAlive(): bool
    {
        $last = self::lastBeatAt();
        $graceMinutes = (int) config('ipodarbar.scheduler.heartbeat_grace', 15);

        return $last !== null && $last->gt(now()->subMinutes($graceMinutes));
    }
}
