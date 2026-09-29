<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves public pages from the full-page cache for anonymous visitors. Only plain URLs
 * (no query string other than ?page=N) are cached, so searches and filters always render
 * fresh. The visitor's own CSRF token is put back into the cached HTML on every hit.
 */
class CachePublicPages
{
    /** Route names (patterns) whose pages look the same for every anonymous visitor. */
    private const ROUTES = [
        'home', 'ipos.*', 'news.index', 'news.category', 'news.show', 'guides.*', 'calculators.*',
        'about', 'contact', 'editorial-policy', 'disclaimer', 'privacy', 'alerts', 'portfolio', 'compare', 'actions.*',
        'blog.index', 'blog.show', 'blog.author',
    ];

    /** JSON and action endpoints under the patterns above. */
    private const EXCLUDED = ['ipos.suggest', 'ipos.vote', 'ipos.ics', 'ipos.calendar-feed'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }

        $url = $request->getSchemeAndHttpHost().$request->getRequestUri();
        $token = (string) $request->session()->token();

        if (($html = PageCache::get($url)) !== null) {
            return response(str_replace(PageCache::TOKEN_PLACEHOLDER, $token, $html), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Page-Cache' => 'HIT',
            ]);
        }

        $response = $next($request);

        if ($this->storable($response) && $token !== '') {
            PageCache::put($url, str_replace($token, PageCache::TOKEN_PLACEHOLDER, (string) $response->getContent()));
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }

    private function cacheable(Request $request): bool
    {
        if (! PageCache::enabled() || ! $request->isMethod('GET') || ! $request->hasSession()) {
            return false;
        }
        if (! $request->routeIs(...self::ROUTES) || $request->routeIs(...self::EXCLUDED)) {
            return false;
        }
        // Signed-in users (admins) and requests carrying flashed messages see personal content.
        if ($request->user() !== null || ! empty($request->session()->get('_flash.old', []))) {
            return false;
        }

        $query = $request->query();

        return array_diff(array_keys($query), ['page']) === []
            && (! isset($query['page']) || (is_string($query['page']) && preg_match('/^[1-9]\d{0,3}$/', $query['page'])));
    }

    private function storable(Response $response): bool
    {
        return $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && $response->headers->getCookies() === [];
    }
}
