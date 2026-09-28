<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the full-page cache after any successful admin change (IPO edits, news edits,
 * settings, manual syncs), so visitors see the change straight away.
 */
class FlushPageCacheAfterWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && $response->getStatusCode() < 400) {
            PageCache::flush();
        }

        return $response;
    }
}
