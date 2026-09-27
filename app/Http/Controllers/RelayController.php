<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\IpoSyncService;
use App\Services\LogoService;
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

    private function authorizeRelay(Request $request): void
    {
        $token = (string) config('ipodarbar.ipo_api.push_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 404);
    }
}
