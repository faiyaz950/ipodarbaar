<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\IpoPageImporter;
use App\Services\IpoSyncService;
use App\Services\LogoService;
use App\Support\PageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives IPO API pages and logo banners relayed from outside (see
 * .github/workflows/ipo-relay.yml) for hosts whose firewall can't reach the IPO API.
 * Every action is hidden behind a 404 until IPO_PUSH_TOKEN is set and sent as a bearer token.
 */
class RelayController extends Controller
{
    public function ipos(Request $request, IpoSyncService $sync): JsonResponse
    {
        $this->authorizeRelay($request);

        $request->validate([
            'data' => ['present', 'array', 'max:200'],
            'data.*' => ['array'],
        ]);

        return response()->json(['imported' => $sync->import($request->input('data'))]);
    }

    /** IPOs shown in the lists whose logo thumbnail hasn't been built yet. */
    public function missingLogos(Request $request, LogoService $logos): JsonResponse
    {
        $this->authorizeRelay($request);

        $missing = Ipo::query()
            ->whereNotNull('image_url')
            ->where(fn ($q) => $q->active()->orWhere('listing_date', '>=', now()->subDays(45)->toDateString()))
            ->get()
            ->reject(fn (Ipo $ipo) => $logos->exists($ipo) || $logos->hasFailed($ipo))
            ->take(150)
            ->map(fn (Ipo $ipo) => ['api_id' => $ipo->api_id, 'url' => $ipo->bannerUrl()])
            ->values();

        return response()->json($missing);
    }

    public function logo(Request $request, Ipo $ipo, LogoService $logos): JsonResponse
    {
        $this->authorizeRelay($request);

        $request->validate(['banner' => ['required', 'image', 'max:4096']]);

        return response()->json(['stored' => $logos->storeBanner($ipo, $request->file('banner')->get()) !== null]);
    }

    /** IPOs without a registrar whose source page hasn't been checked recently, newest first. */
    /**
     * Source IPO pages to read: never read yet, or current IPOs whose page may have gained
     * details (lead managers, financials) since the last read. Current IPOs come first.
     */
    public function stalePages(Request $request): JsonResponse
    {
        $this->authorizeRelay($request);

        $limit = min(max((int) $request->query('limit', 30), 1), 1500);
        $refreshBefore = now()->subHours(12);

        $stale = Ipo::query()
            ->where(fn ($q) => $q->whereNull('page_synced_at')
                ->orWhere(fn ($q) => $q->active()->where('page_synced_at', '<', $refreshBefore)))
            ->orderByDesc('open_date')
            ->get()
            ->sortByDesc(fn (Ipo $ipo) => in_array($ipo->status(), ['upcoming', 'open', 'closed'], true))
            ->take($limit)
            ->map(fn (Ipo $ipo) => ['api_id' => $ipo->api_id, 'url' => config('ipodarbar.ipo_api.page_url').$ipo->slug])
            ->values();

        return response()->json($stale);
    }

    /** Receives a gzipped source IPO page and fills whatever details are still missing. */
    public function page(Request $request, Ipo $ipo, IpoPageImporter $importer): JsonResponse
    {
        $this->authorizeRelay($request);

        $request->validate(['page' => ['required', 'file', 'max:4096']]);

        $bytes = $request->file('page')->get();
        $html = @gzdecode($bytes);
        $filled = $importer->import($ipo, $html === false ? $bytes : $html);

        if ($filled !== []) {
            PageCache::flush();
        }

        return response()->json(['filled' => $filled]);
    }

    private function authorizeRelay(Request $request): void
    {
        $token = (string) config('ipodarbar.ipo_api.push_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 404);
    }
}
