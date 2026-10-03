<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a user agent, referrer and path into the labels the analytics reports group by.
 */
class VisitClassifier
{
    private const BOTS = '/bot\b|bot\/|crawl|spider|slurp|mediapartners|headless|lighthouse|pagespeed|pingdom|uptime|monitor|python|curl|wget|httpclient|okhttp|java\/|go-http|axios|node-fetch|scrapy|facebookexternalhit|whatsapp\/|telegrambot|preview/i';

    /** Referrer hosts mapped to the names shown in reports (first match wins). */
    private const SOURCES = [
        '/(^|\.)news\.google\./' => 'Google News',
        '/(^|\.)google\.|googlequicksearchbox|googleusercontent/' => 'Google',
        '/(^|\.)bing\.com$/' => 'Bing',
        '/(^|\.)duckduckgo\.com$/' => 'DuckDuckGo',
        '/(^|\.)yahoo\./' => 'Yahoo',
        '/(^|\.)yandex\./' => 'Yandex',
        '/whatsapp|^wa\.me$/' => 'WhatsApp',
        '/(^|\.)t\.me$|telegram/' => 'Telegram',
        '/(^|\.)facebook\.com$|^fb\.me$|^m\.facebook/' => 'Facebook',
        '/(^|\.)instagram\.com$/' => 'Instagram',
        '/(^|\.)(x|twitter)\.com$|^t\.co$/' => 'X (Twitter)',
        '/(^|\.)linkedin\.com$|^lnkd\.in$/' => 'LinkedIn',
        '/(^|\.)youtube\.com$|^youtu\.be$/' => 'YouTube',
        '/(^|\.)reddit\.com$/' => 'Reddit',
        '/(^|\.)quora\.com$/' => 'Quora',
        '/chatgpt\.com$|openai\.com$|perplexity\.ai$|claude\.ai$|gemini\.google/' => 'AI assistants',
    ];

    /** Route names grouped into the site's sections (patterns, first match wins). */
    private const SECTIONS = [
        'home' => 'Home',
        'ipos.show' => 'IPO pages',
        'ipos.gmp*' => 'GMP',
        'ipos.*' => 'IPO lists',
        'compare' => 'IPO lists',
        'blog.*' => 'Blog',
        'news.*' => 'News',
        'calculators.*' => 'Calculators',
        'guides.*' => 'Guides',
        'actions.*' => 'Buyback, rights & NCD',
        'search' => 'Search',
        'portfolio' => 'Tools',
        'watchlist' => 'Tools',
        'alerts' => 'Tools',
    ];

    public static function isBot(string $userAgent): bool
    {
        return $userAgent === '' || (bool) preg_match(self::BOTS, $userAgent);
    }

    public static function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua) => 'Mobile',
            default => 'Desktop',
        };
    }

    public static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'FBAN') || str_contains($ua, 'FBAV') => 'Facebook app',
            str_contains($ua, 'Instagram') => 'Instagram app',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'EdgiOS') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'UCBrowser') => 'UC Browser',
            str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Other',
        };
    }

    public static function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Other',
        };
    }

    /**
     * @return array{source: string, host: ?string, external: bool}
     */
    public static function source(?string $referrer, string $ownHost): array
    {
        $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));
        if (str_starts_with((string) $referrer, 'android-app://')) {
            $host = strtolower(substr((string) $referrer, strlen('android-app://')));
            $host = explode('/', $host)[0];
        }
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === '') {
            return ['source' => 'Direct', 'host' => null, 'external' => true];
        }
        if ($host === preg_replace('/^www\./', '', strtolower($ownHost))) {
            return ['source' => 'Internal', 'host' => null, 'external' => false];
        }
        foreach (self::SOURCES as $pattern => $name) {
            if (preg_match($pattern, $host)) {
                return ['source' => $name, 'host' => substr($host, 0, 120), 'external' => true];
            }
        }

        return ['source' => substr($host, 0, 40), 'host' => substr($host, 0, 120), 'external' => true];
    }

    /**
     * @return array{route: ?string, section: string}
     */
    public static function page(string $path): array
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (Throwable) {
            return ['route' => null, 'section' => 'Not found'];
        }

        $name = $route->getName();
        foreach (self::SECTIONS as $pattern => $section) {
            if ($name && Str::is($pattern, $name)) {
                return ['route' => $name, 'section' => $section];
            }
        }

        return ['route' => $name, 'section' => 'Other pages'];
    }
}
