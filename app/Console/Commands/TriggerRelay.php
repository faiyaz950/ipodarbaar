<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('relay:trigger {--full : Relay every page instead of the latest ones}')]
#[Description('Start the GitHub Actions IPO relay (GitHub drops many scheduled runs, so the server kicks it off)')]
class TriggerRelay extends Command
{
    public function handle(): int
    {
        $relay = config('ipodarbar.relay');

        if (blank($relay['token']) || blank($relay['repo'])) {
            $this->line('Relay trigger is not configured (GITHUB_DISPATCH_TOKEN); skipping.');

            return self::SUCCESS;
        }

        $response = Http::withToken($relay['token'])
            ->acceptJson()
            ->timeout(15)
            ->post("https://api.github.com/repos/{$relay['repo']}/actions/workflows/{$relay['workflow']}/dispatches", [
                'ref' => $relay['ref'],
                'inputs' => ['full' => $this->option('full') ? 'true' : 'false'],
            ]);

        if ($response->failed()) {
            $this->error("GitHub rejected the relay trigger (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $this->info('Relay workflow started'.($this->option('full') ? ' (full run).' : '.'));

        return self::SUCCESS;
    }
}
