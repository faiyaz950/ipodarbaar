<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends visitors on old or alternate hostnames (config ipodarbar.redirect_hosts) to APP_URL.
 * Only GET/HEAD are redirected so in-flight form posts and the relay keep working.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = config('ipodarbar.redirect_hosts', []);

        if ($hosts !== [] && $request->isMethodCacheable() && in_array(strtolower($request->getHost()), $hosts, true)) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
