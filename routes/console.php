<?php

use Illuminate\Support\Facades\Schedule;

// Refresh the latest IPOs (GMP, dates) frequently and everything once a day.
Schedule::command('ipo:sync')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ipo:sync --full')->dailyAt('03:30')->withoutOverlapping();
