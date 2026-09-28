<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Full-page cache for anonymous visitors. Rendered HTML is stored gzipped per URL with the
 * CSRF token swapped for a placeholder, so each visitor gets their own token on a hit.
 * The whole cache is flushed whenever IPO data, news edits or settings change.
 */
class PageCache
{
    public const TOKEN_PLACEHOLDER = '__DARBAAR_CSRF_TOKEN__';

    public static function enabled(): bool
    {
        return (bool) config('ipodarbar.page_cache.enabled');
    }

    public static function store(): Repository
    {
        return Cache::store(config('ipodarbar.page_cache.store'));
    }

    public static function key(string $url): string
    {
        return 'page:'.sha1($url);
    }

    public static function get(string $url): ?string
    {
        $stored = self::store()->get(self::key($url));
        $html = is_string($stored) ? @gzdecode($stored) : false;

        return $html === false ? null : $html;
    }

    public static function put(string $url, string $html): void
    {
        self::store()->put(self::key($url), gzencode($html, 6), (int) config('ipodarbar.page_cache.ttl'));
    }

    public static function forget(string $url): void
    {
        if (self::enabled()) {
            self::store()->forget(self::key($url));
        }
    }

    public static function flush(): void
    {
        if (self::enabled()) {
            self::store()->flush();
        }
    }
}
