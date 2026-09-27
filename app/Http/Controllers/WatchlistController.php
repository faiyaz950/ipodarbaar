<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The watchlist itself lives in the visitor's browser (localStorage); the server only
 * renders the IPOs it is asked about, so no account or cookie is needed.
 */
class WatchlistController extends Controller
{
    public const MAX_ITEMS = 50;

    private const STATUS_ORDER = ['open' => 0, 'closed' => 1, 'upcoming' => 2, 'listed' => 3];

    private const AGENDA_STEPS = ['IPO Opens', 'IPO Closes', 'Basis of Allotment', 'Listing Date'];

    public function index(): View
    {
        return view('watchlist.index');
    }

    public function items(Request $request): Response
    {
        $slugs = collect(explode(',', $request->string('slugs')->toString()))
            ->map(fn (string $slug): string => trim($slug))
            ->filter(fn (string $slug): bool => (bool) preg_match('/^[a-z0-9-]{1,120}$/', $slug))
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values();

        $ipos = $slugs->isEmpty() ? collect() : Ipo::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (Ipo $ipo): array => [
                self::STATUS_ORDER[$ipo->status()],
                $ipo->open_date?->timestamp ?? PHP_INT_MAX,
            ])
            ->values();

        return response()
            ->view('watchlist.items', [
                'ipos' => $ipos,
                'agenda' => $this->agenda($ipos),
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Key dates of the watched IPOs over the next two weeks, soonest first.
     *
     * @param  Collection<int, Ipo>  $ipos
     * @return Collection<int, array{date: Carbon, label: string, tentative: bool, ipo: Ipo}>
     */
    private function agenda(Collection $ipos): Collection
    {
        $from = today();
        $to = today()->addDays(14);

        return $ipos
            ->flatMap(fn (Ipo $ipo): Collection => collect($ipo->timeline())
                ->filter(fn (array $step): bool => in_array($step['label'], self::AGENDA_STEPS, true)
                    && $step['date'] !== null
                    && $step['date']->between($from, $to))
                ->map(fn (array $step): array => [
                    'date' => $step['date'],
                    'label' => $step['label'],
                    'tentative' => $step['tentative'],
                    'ipo' => $ipo,
                ]))
            ->sortBy(fn (array $event): int => $event['date']->timestamp)
            ->values();
    }
}
