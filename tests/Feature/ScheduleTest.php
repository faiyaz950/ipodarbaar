<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    /**
     * The live server's cron runs `schedule:run` every 15 minutes, and Laravel only starts tasks
     * due in that exact minute, so a task at 08:50 or 10:01 would silently never run.
     */
    public function test_every_task_falls_on_a_quarter_hour(): void
    {
        $events = app(Schedule::class)->events();
        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $minute = explode(' ', $event->expression)[0];
            $ok = $minute === '*' || preg_match('#^\*/(15|30|45|60)$#', $minute)
                || collect(explode(',', $minute))->every(fn (string $m): bool => ctype_digit($m) && (int) $m % 15 === 0);

            $this->assertTrue((bool) $ok, "\"{$event->command}\" runs at minute {$minute}, which the 15-minute cron never reaches.");
        }
    }
}
