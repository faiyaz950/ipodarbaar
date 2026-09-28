<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Submits changed URLs to IndexNow (Bing, Yandex, Seznam, Naver…). Google does not use
 * IndexNow; it discovers changes through the sitemaps.
 */
class IndexNowService
{
    /** IndexNow accepts up to 10,000 URLs per request. */
    private const MAX_URLS = 10_000;

    public function enabled(): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9-]{8,128}$/', (string) config('ipodarbar.indexnow.key'));
    }

    public function key(): ?string
    {
        return $this->enabled() ? (string) config('ipodarbar.indexnow.key') : null;
    }

    /**
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urls = array_values(array_unique(array_filter($urls, fn (string $url): bool => parse_url($url, PHP_URL_HOST) === $host)));

        if (! $this->enabled() || $urls === [] || ! $host) {
            return false;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->acceptJson()
                ->post((string) config('ipodarbar.indexnow.endpoint'), [
                    'host' => $host,
                    'key' => $this->key(),
                    'keyLocation' => route('indexnow.key'),
                    'urlList' => array_slice($urls, 0, self::MAX_URLS),
                ]);
        } catch (Throwable $e) {
            Log::warning('IndexNow submission failed: '.$e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            Log::warning('IndexNow rejected the submission', ['status' => $response->status()]);
        }

        return $response->successful();
    }
}
