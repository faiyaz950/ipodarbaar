<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsRollup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:rollup')]
#[Description('Add up finished days of page views into daily totals and delete raw rows past the retention period')]
class RollupAnalytics extends Command
{
    public function handle(AnalyticsRollup $rollup): int
    {
        $deleted = $rollup->nightly();
        $this->info("Daily totals are up to date; removed {$deleted} old page views.");

        return self::SUCCESS;
    }
}
