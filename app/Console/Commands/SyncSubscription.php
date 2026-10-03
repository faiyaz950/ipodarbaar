<?php

namespace App\Console\Commands;

use App\Services\Market\SubscriptionSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('ipo:subscription {--backfill= : Link NSE symbols and fetch final figures for IPOs that opened since this date}')]
#[Description('Update IPO subscription (QIB, NII, retail, total) from NSE')]
class SyncSubscription extends Command
{
    public function handle(SubscriptionSync $sync): int
    {
        if ($since = $this->option('backfill')) {
            $result = $sync->backfill(Carbon::parse((string) $since));
            $this->info("Linked {$result['linked']} NSE symbols; filled subscription for {$result['updated']} IPOs.");

            return self::SUCCESS;
        }

        $result = $sync->run();
        $this->info("NSE lists {$result['issues']} open issues; linked {$result['linked']}, updated {$result['updated']}.");

        return self::SUCCESS;
    }
}
