<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\IndexNowService;
use App\Services\IpoStatsService;
use App\Services\NewsService;
use App\Support\Calculators;
use App\Support\Guides;
use App\Support\IpoHubs;
use App\Support\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Crawler-facing files: robots.txt, ads.txt and the XML sitemaps.
 */
class SeoController extends Controller
{
    /** Google's certification authority ID for AdSense sellers. */
    private const ADSENSE_CERT_ID = 'f08c47fec0942fa0';

    /** Child sitemaps listed in /sitemap.xml. */
    public const SITEMAPS = ['pages', 'ipos', 'news', 'news-archive'];

    /** Pages of 100 stories included in the news archive sitemap. */
    private const NEWS_ARCHIVE_PAGES = 10;

    private const DISALLOWED_PATHS = ['/admin', '/ipo/search/suggest', '/shorts/feed', '/watchlist/items', '/ipo-portfolio/prices', '/subscribe', '/unsubscribe'];

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (self::DISALLOWED_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return $this->text(implode("\n", $lines)."\n");
    }

    /**
     * IndexNow key file (its location is sent with every submission).
     */
    public function indexNowKey(IndexNowService $indexNow): Response
    {
        abort_unless($indexNow->enabled(), 404);

        return $this->text((string) $indexNow->key());
    }

    public function adsTxt(Settings $settings): Response
    {
        $client = (string) $settings->get('ads.client');
        abort_unless(str_starts_with($client, 'ca-pub-'), 404);

        return $this->text('google.com, '.substr($client, 3).', DIRECT, '.self::ADSENSE_CERT_ID."\n");
    }

    public function sitemapIndex(): Response
    {
        $sitemaps = array_map(fn (string $part): string => route('sitemaps.show', $part), self::SITEMAPS);

        return response()->view('sitemap-index', ['sitemaps' => $sitemaps])->header('Content-Type', 'application/xml');
    }

    public function sitemap(string $part, IpoStatsService $stats, NewsService $news): Response
    {
        if ($part === 'news') {
            return $this->googleNewsSitemap($news);
        }

        $urls = match ($part) {
            'pages' => $this->pageUrls($stats),
            'ipos' => $this->ipoUrls(),
            'news-archive' => array_map(
                fn (array $url): array => ['loc' => $url['loc'], 'lastmod' => $url['lastmod'] ? Carbon::parse($url['lastmod']) : null],
                Cache::remember('sitemap:news-archive:v2', now()->addHours(6), fn (): array => $this->newsArchiveUrls($news))
            ),
        };

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    /**
     * IPO pages with the date their content last changed (the sync touches every row, so
     * updated_at alone would make every lastmod "now" and Google would learn to ignore it).
     * Recent IPOs also list their share card for image search.
     *
     * @return array<int, array{loc: string, lastmod: Carbon|null, image?: string|null}>
     */
    private function ipoUrls(): array
    {
        $recent = now()->subDays(120);

        return Ipo::query()
            ->with('detail:id,ipo_id,updated_at')
            ->orderByDesc('api_id')
            ->limit(5000)
            ->get()
            ->map(fn (Ipo $ipo): array => [
                'loc' => route('ipos.show', $ipo->slug),
                'lastmod' => collect([$ipo->source_updated_at, $ipo->subscription_updated_at, $ipo->detail?->updated_at])->filter()->max()
                    ?? $ipo->source_created_at,
                'image' => ($ipo->open_date === null || $ipo->open_date->gte($recent)) ? $ipo->shareImageUrl() : null,
                'image_title' => $ipo->name.' IPO',
            ])
            ->all();
    }

    /**
     * Stories from the last 48 hours in Google News sitemap format.
     */
    private function googleNewsSitemap(NewsService $news): Response
    {
        $cutoff = now()->subHours(48);
        $items = collect($news->latest(100)['items'])
            ->filter(fn (array $item): bool => $item['date'] !== null && $item['date']->gte($cutoff))
            ->values();

        return response()->view('sitemap-news', ['items' => $items])->header('Content-Type', 'application/xml');
    }

    /**
     * Dates are ISO strings: the cache does not unserialize objects (cache.serializable_classes).
     *
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    private function newsArchiveUrls(NewsService $news): array
    {
        $urls = [];
        for ($page = 1; $page <= self::NEWS_ARCHIVE_PAGES; $page++) {
            $result = $news->latest(100, $page);
            foreach ($result['items'] as $item) {
                $urls[] = ['loc' => $item['url'], 'lastmod' => ($item['updated'] ?? $item['date'])?->toAtomString()];
            }
            if (! $result['has_more']) {
                break;
            }
        }

        return $urls;
    }

    /**
     * @return array<int, array{loc: string, lastmod: Carbon|null}>
     */
    private function pageUrls(IpoStatsService $stats): array
    {
        $urls = [
            route('home'), route('ipos.index'), route('ipos.gmp'), route('ipos.gmp.mainboard'), route('ipos.gmp.sme'),
            route('ipos.listing-today'), route('ipos.sme-dashboard'), route('ipos.calendar'), route('ipos.report-card'), route('portfolio'),
            route('news.index'), route('news.shorts'), route('calculators.index'), route('guides.index'), route('alerts'),
            route('about'), route('contact'), route('editorial-policy'), route('disclaimer'), route('privacy'),
        ];

        foreach (array_keys(IpoHubs::all()) as $hub) {
            $urls[] = route('ipos.'.$hub);
        }
        foreach (array_keys(Guides::all()) as $slug) {
            $urls[] = route('guides.show', $slug);
        }
        foreach (config('ipodarbar.news_categories') as $category) {
            $urls[] = route('news.category', $category['slug']);
        }

        foreach ($stats->years() as $year) {
            $urls[] = route('ipos.year', $year);
        }
        foreach (array_keys(Calculators::all()) as $slug) {
            $urls[] = route('calculators.show', $slug);
        }

        return array_map(fn (string $url): array => ['loc' => $url, 'lastmod' => null], $urls);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
