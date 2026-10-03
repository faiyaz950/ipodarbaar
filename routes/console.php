<?php

use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Schedule;

/*
 * One cron entry (`* * * * * php artisan schedule:run`) drives everything below.
 * Tasks run one after another, so the queue worker is last and bounded to 50 seconds.
 */

Schedule::call(fn () => SchedulerHeartbeat::beat())->name('scheduler-heartbeat')->everyMinute();

// Latest IPOs (GMP, dates) every 15 minutes; the full catalogue once a day.
Schedule::command('ipo:sync')->everyFifteenMinutes()->withoutOverlapping(10)->when(fn () => config('ipodarbar.ipo_api.pull'));
Schedule::command('ipo:sync --full')->dailyAt('03:30')->withoutOverlapping(30)->when(fn () => config('ipodarbar.ipo_api.pull'));

// Start the GitHub relay (source pages, logos) ourselves; GitHub skips most of its own scheduled runs.
Schedule::command('relay:trigger')->everyFifteenMinutes()->when(fn () => filled(config('ipodarbar.relay.token')));
Schedule::command('relay:trigger --full')->dailyAt('03:30')->when(fn () => filled(config('ipodarbar.relay.token')));

// Telegram channel: new IPO alerts after each sync, a morning digest and an evening GMP board.
Schedule::command('darbaar:telegram new')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('darbaar:telegram digest')->dailyAt('09:00');
Schedule::command('darbaar:telegram gmp')->dailyAt('18:30');

// Email digest for confirmed subscribers.
Schedule::command('darbaar:digest daily')->dailyAt('08:00');
Schedule::command('darbaar:digest weekly')->weeklyOn(1, '08:05');

// NSE subscription while IPOs take bids; listing prices from the 10 AM pre-open session and the evening bhavcopies.
Schedule::command('ipo:subscription')->weekdays()->everyFifteenMinutes()->between('10:00', '19:45')->withoutOverlapping(10);
Schedule::command('ipo:listing-prices --morning')->weekdays()->at('10:01');
Schedule::command('ipo:listing-prices --morning')->weekdays()->at('10:16');
Schedule::command('ipo:listing-prices')->weekdays()->at('19:00');
Schedule::command('ipo:listing-prices')->weekdays()->at('21:30');

// Blog: the "IPO today" update on weekday mornings and the week-ahead calendar on Sundays.
Schedule::command('blog:auto daily')->weekdays()->at('08:50')->withoutOverlapping(10);
Schedule::command('blog:auto weekly')->sundays()->at('10:00')->withoutOverlapping(10);

// Tell IndexNow search engines (Bing, Yandex…) about changed pages soon after each sync.
Schedule::command('seo:indexnow')->everyThirtyMinutes()->withoutOverlapping(10);

// Sends queued mail (digests, confirmations) without a long-running worker process.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);

// Expired page-cache files are only removed when read; clear the page store hourly.
Schedule::command('cache:clear pages')->hourly();
