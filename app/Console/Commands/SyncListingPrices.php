<?php

namespace App\Console\Commands;

use App\Services\Market\ListingPriceSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('ipo:listing-prices {--morning : Read the 10 AM special pre-open session for today\'s mainboard listings} {--date= : Read the bhavcopies for this day (default today)} {--since= : Backfill from this date} {--until= : End of the backfill (default today)}')]
#[Description('Record IPO listing prices and listing-day closes from NSE and BSE')]
class SyncListingPrices extends Command
{
    public function handle(ListingPriceSync $sync): int
    {
        if ($this->option('morning')) {
            $this->info('Recorded '.$sync->morning().' listing prices from the pre-open session.');

            return self::SUCCESS;
        }

        if ($since = $this->option('since')) {
            $until = $this->option('until') ? Carbon::parse((string) $this->option('until')) : now();
            $this->info('Recorded '.$sync->backfill(Carbon::parse((string) $since), $until).' listings.');

            return self::SUCCESS;
        }

        $date = $this->option('date') ? Carbon::parse((string) $this->option('date')) : now();
        $this->info('Recorded '.$sync->endOfDay($date).' listings for '.$date->format('j M Y').'.');

        return self::SUCCESS;
    }
}
