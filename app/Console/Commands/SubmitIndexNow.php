<?php

namespace App\Console\Commands;

use App\Models\Ipo;
use App\Services\IndexNowService;
use App\Services\NewsService;
use App\Support\IpoHubs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('seo:indexnow')]
#[Description('Submit IPO pages, hubs and news that changed since the last run to IndexNow')]
class SubmitIndexNow extends Command
{
    private const LAST_RUN_KEY = 'indexnow:last_run';

    public function handle(IndexNowService $indexNow, NewsService $news): int
    {
        if (! $indexNow->enabled()) {
            $this->line('IndexNow is not configured (INDEXNOW_KEY); skipping.');

            return self::SUCCESS;
        }

        $since = Carbon::parse(Cache::get(self::LAST_RUN_KEY, now()->subDay()->toIso8601String()));
        $startedAt = now();

        $ipoUrls = Ipo::query()
            ->where(fn ($q) => $q->where('source_updated_at', '>', $since)
                ->orWhere('created_at', '>', $since)
                ->orWhere('subscription_updated_at', '>', $since))
            ->limit(500)
            ->get(['slug'])
            ->map(fn (Ipo $ipo): string => route('ipos.show', $ipo->slug))
            ->all();

        $newsUrls = collect($news->latest(50)['items'])
            ->filter(fn (array $item): bool => $item['date'] !== null && $item['date']->gt($since))
            ->pluck('url')
            ->all();

        $hubUrls = $ipoUrls === [] ? [] : array_merge(
            [route('home'), route('ipos.gmp'), route('ipos.gmp.mainboard'), route('ipos.gmp.sme'), route('ipos.listing-today'), route('ipos.calendar')],
            array_map(fn (string $hub): string => route('ipos.'.$hub), array_keys(IpoHubs::all()))
        );

        $urls = array_merge($ipoUrls, $newsUrls, $hubUrls, $newsUrls === [] ? [] : [route('news.index')]);

        if ($urls === []) {
            Cache::forever(self::LAST_RUN_KEY, $startedAt->toIso8601String());
            $this->line('Nothing changed since '.$since->toDateTimeString().'.');

            return self::SUCCESS;
        }

        if (! $indexNow->submit($urls)) {
            $this->error('IndexNow submission failed; will retry these URLs next run.');

            return self::FAILURE;
        }

        Cache::forever(self::LAST_RUN_KEY, $startedAt->toIso8601String());
        $this->info('Submitted '.count(array_unique($urls)).' URL(s) to IndexNow.');

        return self::SUCCESS;
    }
}
