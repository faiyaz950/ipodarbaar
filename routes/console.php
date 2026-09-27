<?php

use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Schedule;

/*
 * One cron entry (`* * * * * php artisan schedule:run`) drives everything below.
 * Tasks run one after another, so the queue worker is last and bounded to 50 seconds.
 */

Schedule::call(fn () => SchedulerHeartbeat::beat())->name('scheduler-heartbeat')->everyMinute();

// Latest IPOs (GMP, dates) every 15 minutes; the full catalogue once a day.
Schedule::command('ipo:sync')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('ipo:sync --full')->dailyAt('03:30')->withoutOverlapping(30);

// Telegram channel: new IPO alerts after each sync, a morning digest and an evening GMP board.
Schedule::command('darbaar:telegram new')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('darbaar:telegram digest')->dailyAt('09:00');
Schedule::command('darbaar:telegram gmp')->dailyAt('18:30');

// Email digest for confirmed subscribers.
Schedule::command('darbaar:digest daily')->dailyAt('08:00');
Schedule::command('darbaar:digest weekly')->weeklyOn(1, '08:05');

// Sends queued mail (digests, confirmations) without a long-running worker process.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
