<?php

namespace App\Http\Controllers;

use App\Services\IpoSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives IPO API pages relayed from outside (see .github/workflows/ipo-relay.yml) for
 * hosts whose firewall can't reach the IPO API directly. Disabled until IPO_PUSH_TOKEN is set.
 */
class IpoPushController extends Controller
{
    public function __invoke(Request $request, IpoSyncService $sync): JsonResponse
    {
        $token = (string) config('ipodarbar.ipo_api.push_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->bearerToken()), 404);

        $request->validate([
            'data' => ['present', 'array', 'max:200'],
            'data.*' => ['array'],
        ]);

        return response()->json(['imported' => $sync->import($request->input('data'))]);
    }
}
