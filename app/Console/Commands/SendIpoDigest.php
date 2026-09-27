<?php

namespace App\Console\Commands;

use App\Jobs\SendDigestMail;
use App\Models\NotificationLog;
use App\Models\Subscriber;
use App\Services\IpoDigestBuilder;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('darbaar:digest {frequency=daily : daily or weekly}')]
#[Description('Queue the IPO digest email for confirmed subscribers (once per day or week)')]
class SendIpoDigest extends Command
{
    public function handle(IpoDigestBuilder $builder, Settings $settings): int
    {
        $frequency = (string) $this->argument('frequency');
        if (! array_key_exists($frequency, Subscriber::FREQUENCIES)) {
            $this->error('Frequency must be daily or weekly.');

            return self::FAILURE;
        }

        if (! $settings->enabled('email.digest')) {
            $this->line('Email digests are switched off in Admin → Settings.');

            return self::SUCCESS;
        }

        $digest = $builder->email($frequency);
        if ($digest['is_empty']) {
            $this->line('Nothing to report today; no digest sent.');

            return self::SUCCESS;
        }

        $period = $frequency === 'weekly' ? now()->format('o-\WW') : now()->toDateString();
        if (! NotificationLog::claim('email', "digest:{$frequency}:{$period}")) {
            $this->line("The {$frequency} digest for {$period} was already sent.");

            return self::SUCCESS;
        }

        $queued = 0;
        Subscriber::query()
            ->receiving()
            ->where('frequency', $frequency)
            ->chunkById(200, function (Collection $subscribers) use ($digest, &$queued): void {
                foreach ($subscribers as $subscriber) {
                    SendDigestMail::dispatch($subscriber->id, $digest);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} {$frequency} digest email(s).");

        return self::SUCCESS;
    }
}
