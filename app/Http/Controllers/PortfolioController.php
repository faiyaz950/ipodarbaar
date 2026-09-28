<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The IPO portfolio lives in the visitor's browser (localStorage); the server only
 * supplies the latest price, lot size, GMP and listing price of the IPOs in it.
 */
class PortfolioController extends Controller
{
    public const MAX_ITEMS = 200;

    public function index(): View
    {
        return view('portfolio.index');
    }

    public function prices(Request $request): JsonResponse
    {
        $slugs = collect(explode(',', $request->string('slugs')->toString()))
            ->map(fn (string $slug): string => trim($slug))
            ->filter(fn (string $slug): bool => (bool) preg_match('/^[a-z0-9-]{1,120}$/', $slug))
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values();

        $ipos = $slugs->isEmpty() ? collect() : Ipo::query()->whereIn('slug', $slugs)->get();

        return response()
            ->json($ipos->mapWithKeys(fn (Ipo $ipo): array => [$ipo->slug => [
                'name' => $ipo->name,
                'url' => $ipo->url(),
                'type' => $ipo->typeLabel(),
                'status' => $ipo->status(),
                'statusLabel' => $ipo->statusLabel(),
                'price' => $ipo->price,
                'lot' => $ipo->lot_size,
                'gmp' => $ipo->gmp,
                'listingPrice' => $ipo->listing_price,
                'listingDate' => $ipo->listing_date?->toDateString(),
            ]]))
            ->header('Cache-Control', 'public, max-age=60');
    }
}
