<?php

namespace App\Http\Controllers;

use App\Services\Analytics\Tracker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receives the page views and events app.js sends with navigator.sendBeacon. The body is
 * JSON sent as text/plain, so the browser needs no preflight; the reply is always empty.
 */
class AnalyticsBeaconController extends Controller
{
    public function view(Request $request, Tracker $tracker): Response
    {
        $tracker->view($request, $this->payload($request));

        return response()->noContent();
    }

    public function event(Request $request, Tracker $tracker): Response
    {
        $tracker->event($request, $this->payload($request));

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $body = $request->getContent();
        $data = strlen($body) <= 4096 ? json_decode($body, true) : null;

        return is_array($data) ? $data : [];
    }
}
