<?php

namespace App\Http\Middleware;

use App\Services\IpoSyncService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps IPO data fresh even when the scheduler isn't running.
 */
class RefreshIpoData
{
    public function __construct(private IpoSyncService $sync) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->ajax()) {
            $this->sync->ensureFresh();
        }

        return $next($request);
    }
}
