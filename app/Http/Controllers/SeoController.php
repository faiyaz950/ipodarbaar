<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\IpoStatsService;
use App\Services\NewsService;
use App\Support\Calculators;
use App\Support\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Crawler-facing files: robots.txt, ads.txt and the XML sitemaps.
 */
class SeoController extends Controller
{
    /** Google's certification authority ID for AdSense sellers. */
    private const ADSENSE_CERT_ID = 'f08c47fec0942fa0';

    private const DISALLOWED_PATHS = ['/admin', '/ipo/search/suggest', '/shorts/feed', '/watchlist/items', '/subscribe', '/unsubscribe'];

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

    public function adsTxt(Settings $settings): Response
    {
        $client = (string) $settings->get('ads.client');
        abort_unless(str_starts_with($client, 'ca-pub-'), 404);

        return $this->text('google.com, '.substr($client, 3).', DIRECT, '.self::ADSENSE_CERT_ID."\n");
    }

    public function sitemapIndex(): Response
    {
        $sitemaps = array_map(fn (string $part): string => route('sitemaps.show', $part), ['pages', 'ipos', 'news']);

        return response()->view('sitemap-index', ['sitemaps' => $sitemaps])->header('Content-Type', 'application/xml');
    }

    public function sitemap(string $part, IpoStatsService $stats, NewsService $news): Response
    {
        $urls = match ($part) {
            'pages' => $this->pageUrls($stats),
            'ipos' => Ipo::query()->orderByDesc('api_id')->limit(5000)->get(['slug', 'updated_at'])
                ->map(fn (Ipo $ipo): array => ['loc' => route('ipos.show', $ipo->slug), 'lastmod' => $ipo->updated_at])
                ->all(),
            'news' => array_map(fn (array $item): array => ['loc' => $item['url'], 'lastmod' => $item['date']], $news->latest(100)['items']),
        };

        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    /**
     * @return array<int, array{loc: string, lastmod: Carbon|null}>
     */
    private function pageUrls(IpoStatsService $stats): array
    {
        $urls = [
            route('home'), route('ipos.index'), route('ipos.type', 'mainboard'), route('ipos.type', 'sme'),
            route('ipos.gmp'), route('ipos.calendar'), route('ipos.report-card'), route('news.index'), route('news.shorts'),
            route('calculators.index'), route('about'), route('disclaimer'), route('privacy'),
        ];

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
