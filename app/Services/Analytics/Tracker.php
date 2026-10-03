<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records page views and events sent by the site's script. No cookies and no IP addresses
 * are stored: a visitor is a hash of the day, IP and browser, so it changes every day.
 */
class Tracker
{
    /** A reload of the same page by the same visitor within this many seconds counts once. */
    private const REPEAT_SECONDS = 30;

    /** Paths that are never counted (admin, internal endpoints, files). */
    private const IGNORED = '#^/(admin|internal|d/|up$|logos/|og/|assets/|uploads/|fonts/|images/|icons/|sw\.js|manifest)#';

    /**
     * @param  array<string, mixed>  $data  p: path, t: title, r: referrer, u: [utm_source, utm_medium, utm_campaign]
     */
    public function view(Request $request, array $data): bool
    {
        $ua = (string) $request->userAgent();
        $path = $this->path($data['p'] ?? null);
        if ($path === null || VisitClassifier::isBot($ua)) {
            return false;
        }

        $visitor = $this->visitor($request);
        if (! Cache::add('pv:'.$visitor.':'.md5($path), 1, self::REPEAT_SECONDS)) {
            return false;
        }

        $page = VisitClassifier::page($path);
        $source = VisitClassifier::source(is_string($data['r'] ?? null) ? $data['r'] : null, (string) $request->getHost());
        $utm = array_values(array_pad(array_map(fn ($v): ?string => is_string($v) && $v !== '' ? Str::limit(strip_tags($v), 57, '') : null, (array) ($data['u'] ?? [])), 3, null));
        $now = now();

        DB::table('page_views')->insert([
            'path' => $path,
            'route' => $page['route'],
            'section' => $page['section'],
            'source' => $utm[0] && $source['source'] === 'Direct' ? Str::limit(Str::title($utm[0]), 37, '') : $source['source'],
            'referrer_host' => $source['host'],
            'is_entry' => $source['external'],
            'visitor' => $visitor,
            'device' => VisitClassifier::device($ua),
            'browser' => VisitClassifier::browser($ua),
            'os' => VisitClassifier::os($ua),
            'utm_source' => $utm[0],
            'utm_medium' => $utm[1],
            'utm_campaign' => $utm[2],
            'created_at' => $now,
        ]);

        $title = trim(preg_replace('/\s*\|\s*IPO Darbaar\s*$/', '', strip_tags((string) ($data['t'] ?? ''))) ?? '');
        DB::table('analytics_pages')->upsert(
            [['path' => $path, 'title' => $title !== '' ? Str::limit($title, 187) : null, 'section' => $page['section'], 'last_seen_at' => $now]],
            ['path'],
            ['title', 'section', 'last_seen_at'],
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $data  n: event name, l: label, p: path
     */
    public function event(Request $request, array $data): bool
    {
        $name = is_string($data['n'] ?? null) ? $data['n'] : '';
        $path = $this->path($data['p'] ?? null);
        if (! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $name) || $path === null || VisitClassifier::isBot((string) $request->userAgent())) {
            return false;
        }

        $label = is_scalar($data['l'] ?? null) ? trim(strip_tags((string) $data['l'])) : '';
        DB::table('analytics_events')->insert([
            'name' => $name,
            'label' => $label !== '' ? Str::limit($label, 117) : null,
            'path' => $path,
            'visitor' => $this->visitor($request),
            'created_at' => now(),
        ]);

        return true;
    }

    /** The path part only: no host, query or fragment; null when it isn't a page worth counting. */
    private function path(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }
        $path = '/'.trim((string) parse_url($value, PHP_URL_PATH), '/');
        if (strlen($path) > 255 || preg_match(self::IGNORED, $path) || preg_match('/[^\x21-\x7E]/', $path)) {
            return null;
        }

        return $path;
    }

    private function visitor(Request $request): string
    {
        return substr(hash_hmac('sha256', now()->toDateString().'|'.$request->ip().'|'.$request->userAgent(), (string) config('app.key')), 0, 16);
    }
}
