<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Services\IpoStatsService;
use App\Services\NewsService;
use App\Support\Calculators;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function __invoke(NewsService $news, IpoStatsService $ipoStats)
    {
        $reportYear = $ipoStats->years()[0] ?? null;

        $boards = [];
        foreach (['open', 'upcoming', 'closed', 'listed'] as $status) {
            $boards[$status] = Ipo::inStatus($status)->limit(10)->get();
        }

        $stats = [
            'open' => Ipo::open()->count(),
            'upcoming' => Ipo::upcoming()->count(),
            'closed' => Ipo::closed()->count(),
            'listing_week' => Ipo::query()
                ->whereDate('listing_date', '>=', today()->toDateString())
                ->whereDate('listing_date', '<=', today()->addDays(6)->toDateString())
                ->count(),
        ];

        $topGmp = Ipo::active()->where('gmp', '>', 0)->get()
            ->filter(fn (Ipo $ipo): bool => $ipo->gmpPercent() !== null)
            ->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent())
            ->take(5)
            ->values();

        $latest = $news->latest(13);
        $ipoNews = $news->latest(5, 1, 9);

        return view('home', [
            'boards' => $boards,
            'stats' => $stats,
            'topGmp' => $topGmp,
            'week' => $this->week(),
            'news' => $latest['items'],
            'ipoNews' => $ipoNews['items'],
            'calculators' => Calculators::featured(),
            'categories' => $news->categories(),
            'report' => $reportYear ? $ipoStats->forYear($reportYear) : null,
            'posts' => BlogPost::query()->live()->latest('published_at')->limit(3)->get(),
        ]);
    }

    /** Monday → Sunday of the current week with IPO events per day. */
    private function week(): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6);

        $ipos = Ipo::query()
            ->where(fn ($q) => $q->whereBetween('open_date', [$start->toDateString(), $end->toDateString()])
                ->orWhereBetween('close_date', [$start->toDateString(), $end->toDateString()])
                ->orWhereBetween('listing_date', [$start->toDateString(), $end->toDateString()]))
            ->get();

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $events = [];
            foreach ($ipos as $ipo) {
                foreach (['open_date' => 'Opens', 'close_date' => 'Closes', 'listing_date' => 'Lists'] as $field => $label) {
                    if ($ipo->{$field}?->toDateString() === $key) {
                        $events[] = ['ipo' => $ipo, 'label' => $label, 'kind' => $field];
                    }
                }
            }
            $days[] = ['date' => $d->copy(), 'events' => $events, 'today' => $d->isToday()];
        }

        return $days;
    }
}
