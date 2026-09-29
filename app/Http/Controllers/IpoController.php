<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Ipo;
use App\Models\IpoVote;
use App\Services\IpoDigestBuilder;
use App\Services\NewsService;
use App\Support\IcsCalendar;
use App\Support\IpoHubs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class IpoController extends Controller
{
    public function index(Request $request, ?string $type = null)
    {
        $type = in_array($type, ['mainboard', 'sme'], true) ? $type : $request->query('type');
        $type = in_array($type, ['mainboard', 'sme'], true) ? $type : null;
        $status = array_key_exists((string) $request->query('status'), Ipo::STATUSES) ? $request->query('status') : null;
        $q = trim((string) $request->query('q', ''));

        // Filtered list URLs moved to keyword hubs (/upcoming-ipo, /sme-ipo, ...): send links and rankings there.
        if ($q === '' && ($type !== null || $status !== null)) {
            $hub = $type ?? IpoHubs::keyForStatus($status);
            $query = array_filter([
                'status' => $type !== null ? $status : null,
                'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
            ]);

            return redirect()->route('ipos.'.$hub, $query, 301);
        }

        $ipos = Ipo::query()
            ->search($q)
            ->inStatus(null)
            ->paginate(25)
            ->withQueryString();

        $counts = ['all' => Ipo::query()->search($q)->count()];
        foreach (array_keys(Ipo::STATUSES) as $s) {
            $counts[$s] = Ipo::query()->search($q)->{$s}()->count();
        }

        return view('ipos.index', compact('ipos', 'q', 'counts'));
    }

    /**
     * Keyword landing pages: /upcoming-ipo, /current-ipo, /ipo-allotment-status,
     * /recently-listed-ipo, /sme-ipo and /mainboard-ipo.
     */
    public function hub(Request $request, string $hub, IpoDigestBuilder $digest)
    {
        $config = IpoHubs::find($hub);
        $status = $config['status'];
        $type = $config['type'];

        // Board hubs can be narrowed by status and status hubs by board; those variants are not indexed.
        $filter = null;
        if ($type !== null && $status === null && array_key_exists((string) $request->query('status'), Ipo::STATUSES)) {
            $filter = $status = $request->query('status');
        }
        if ($type === null && $status !== null && in_array($request->query('type'), ['mainboard', 'sme'], true)) {
            $filter = $type = $request->query('type');
        }

        $query = Ipo::query()->ofType($type);
        if ($hub === 'allotment') {
            // Awaiting allotment/listing, plus IPOs listed in the last 10 days (people still check allotment then).
            $window = now()->subDays(10)->toDateString();
            $query->where(fn ($w) => $w->closed()
                ->orWhere(fn ($l) => $l->listed()->whereDate('listing_date', '>=', $window)))
                ->orderByDesc('close_date')->orderBy('name');
        } elseif ($status === null) {
            // Board hubs: live issues first (open, closing soon, upcoming), then the latest listings.
            $query->orderByRaw('case when listing_date is null or listing_date >= ? then 0 else 1 end', [now()->toDateString()])
                ->orderByRaw('open_date is null')->orderByDesc('open_date')->orderByDesc('api_id');
        } else {
            $query->inStatus($status);
        }

        // Upcoming and open IPOs rarely pass a few dozen, so they fit on one page, split by board.
        $ipos = $query->paginate(isset($config['groups']) && $filter === null ? 60 : 25)->withQueryString();

        return view('ipos.hub', [
            'hub' => $hub,
            'config' => $config,
            'ipos' => $ipos,
            'status' => $status,
            'type' => $type,
            'filter' => $filter,
            'fill' => ['count' => $ipos->total()],
            'allotmentDates' => $hub === 'allotment'
                ? $ipos->getCollection()->mapWithKeys(fn (Ipo $ipo): array => [$ipo->id => $digest->allotmentDate($ipo)])
                : collect(),
        ]);
    }

    /**
     * The page is identical for every visitor so it can be served from the page cache; the
     * visitor's own poll vote is remembered in their browser.
     */
    public function show(Ipo $ipo, NewsService $news)
    {
        $related = Ipo::query()
            ->whereKeyNot($ipo->getKey())
            ->inStatus(in_array($ipo->status(), ['open', 'upcoming', 'closed'], true) ? $ipo->status() : 'listed')
            ->limit(6)
            ->get();

        if ($related->count() < 4) {
            $related = $related->merge(Ipo::active()->whereKeyNot($ipo->getKey())->whereNotIn('id', $related->pluck('id'))
                ->orderBy('open_date')->limit(6 - $related->count())->get());
        }

        $ipo->load(['detail', 'financials']);

        $companyNews = $news->mentioning($ipo->name);

        return view('ipos.show', [
            'ipo' => $ipo,
            'gmpTrend' => $ipo->gmpHistory()->get(['date', 'gmp']),
            'poll' => IpoVote::results($ipo),
            'related' => $related,
            'ipoNews' => $companyNews ?: $news->latest(5, 1, 9)['items'],
            'companyNews' => $companyNews !== [],
            'blogPosts' => BlogPost::query()->live()->whereHas('ipos', fn ($q) => $q->whereKey($ipo->id))
                ->latest('published_at')->limit(3)->get(['id', 'title', 'slug', 'category', 'published_at', 'reading_minutes']),
        ]);
    }

    /**
     * Live GMP for every active IPO, or for one board on /mainboard-ipo-gmp and /sme-ipo-gmp.
     */
    public function gmp(IpoDigestBuilder $digest, ?string $type = null)
    {
        $active = Ipo::active()->ofType($type)->get()->sortBy(fn (Ipo $i) => [
            $i->hasGmp() ? 0 : 1,
            ['open' => 0, 'upcoming' => 1, 'closed' => 2][$i->status()] ?? 3,
            $i->close_date?->timestamp ?? PHP_INT_MAX,
        ])->values();

        $recent = Ipo::listed()->ofType($type)->whereNotNull('listing_date')
            ->whereDate('listing_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('listing_date')->get();

        $allotmentDates = $active->mapWithKeys(fn (Ipo $ipo): array => [$ipo->id => $digest->allotmentDate($ipo)]);

        return view('ipos.gmp', compact('active', 'recent', 'allotmentDates', 'type'));
    }

    /**
     * The IPO's opening, closing, allotment and listing dates as a calendar file.
     */
    public function ics(Ipo $ipo): Response
    {
        return $this->calendarResponse((new IcsCalendar($ipo->name.' IPO dates'))->addIpo($ipo), $ipo->slug.'-dates.ics');
    }

    /**
     * Subscribable calendar of IPO dates from 30 days back to 4 months ahead. Narrow it
     * with ?type=mainboard|sme or ?ipos=slug-1,slug-2 (a watchlist, up to 50 IPOs).
     */
    public function calendarFeed(Request $request): Response
    {
        $type = in_array($request->query('type'), ['mainboard', 'sme'], true) ? $request->query('type') : null;
        $slugs = collect(explode(',', (string) $request->query('ipos', '')))
            ->map(fn (string $slug): string => trim($slug))
            ->filter(fn (string $slug): bool => (bool) preg_match('/^[a-z0-9-]{1,120}$/', $slug))
            ->unique()->take(50)->values();

        $from = today()->subDays(30)->toDateString();
        $to = today()->addMonths(4)->toDateString();
        $ipos = Ipo::query()
            ->ofType($type)
            ->when($slugs->isNotEmpty(), fn ($q) => $q->whereIn('slug', $slugs))
            ->where(fn ($q) => $q->where(fn ($d) => $d->whereDate('open_date', '>=', $from)->whereDate('open_date', '<=', $to))
                ->orWhere(fn ($d) => $d->whereDate('listing_date', '>=', $from)->whereDate('listing_date', '<=', $to)))
            ->orderBy('open_date')
            ->get();

        $name = $slugs->isNotEmpty() ? 'My IPO watchlist' : match ($type) {
            'sme' => 'SME IPO calendar',
            'mainboard' => 'Mainboard IPO calendar',
            default => 'IPO calendar',
        };
        $calendar = new IcsCalendar($name.' · IPO Darbaar');
        $ipos->each(fn (Ipo $ipo) => $calendar->addIpo($ipo));

        return $this->calendarResponse($calendar, 'ipo-darbaar-calendar.ics', inline: true);
    }

    private function calendarResponse(IcsCalendar $calendar, string $filename, bool $inline = false): Response
    {
        return response($calendar->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    /**
     * One-page overview of the SME IPO market: live issues, GMP leaders and this year's numbers.
     */
    public function smeDashboard()
    {
        $year = now()->year;
        $thisYear = Ipo::query()->ofType('sme')->whereYear('open_date', $year)->get();
        $withGmp = Ipo::active()->ofType('sme')->whereNotNull('gmp')->get()
            ->filter(fn (Ipo $ipo): bool => $ipo->gmpPercent() !== null);

        $months = [];
        foreach (range(1, now()->month) as $month) {
            $inMonth = $thisYear->filter(fn (Ipo $ipo): bool => $ipo->open_date->month === $month);
            $months[$month] = ['count' => $inMonth->count(), 'raised' => round((float) $inMonth->sum('issue_size'), 2)];
        }

        return view('ipos.sme-dashboard', [
            'year' => $year,
            'open' => Ipo::query()->ofType('sme')->inStatus('open')->get(),
            'upcoming' => Ipo::query()->ofType('sme')->inStatus('upcoming')->get(),
            'listingWeek' => Ipo::query()->ofType('sme')
                ->whereDate('listing_date', '>=', today()->toDateString())
                ->whereDate('listing_date', '<=', today()->addDays(6)->toDateString())
                ->count(),
            'topGmp' => $withGmp->filter(fn (Ipo $ipo): bool => $ipo->gmp > 0)->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent())->take(5)->values(),
            'avgGmp' => $withGmp->isNotEmpty() ? round($withGmp->avg(fn (Ipo $ipo): float => $ipo->gmpPercent()), 1) : null,
            'yearCount' => $thisYear->count(),
            'yearRaised' => round((float) $thisYear->sum('issue_size'), 2),
            'yearShare' => ($total = Ipo::query()->whereYear('open_date', $year)->count()) ? round($thisYear->count() / $total * 100) : null,
            'largest' => $thisYear->filter(fn (Ipo $ipo): bool => (bool) $ipo->issue_size)->sortByDesc('issue_size')->take(5)->values(),
            'months' => $months,
        ]);
    }

    /**
     * IPOs listing today, the rest of this week's listings and the last ten days of listings.
     */
    public function listingToday()
    {
        $today = today()->toDateString();

        return view('ipos.listing-today', [
            'today' => Ipo::query()->whereDate('listing_date', $today)->orderBy('name')->get(),
            'thisWeek' => Ipo::query()
                ->whereDate('listing_date', '>', $today)
                ->whereDate('listing_date', '<=', today()->addDays(7)->toDateString())
                ->orderBy('listing_date')->orderBy('name')->get(),
            'recent' => Ipo::query()
                ->whereDate('listing_date', '<', $today)
                ->whereDate('listing_date', '>=', today()->subDays(10)->toDateString())
                ->orderByDesc('listing_date')->orderBy('name')->get(),
        ]);
    }

    public function calendar(Request $request)
    {
        try {
            $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
        }

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        [$from, $to] = [$gridStart->toDateString(), $gridEnd->toDateString()];

        $ipos = Ipo::query()
            ->where(fn ($q) => $q->whereBetween('open_date', [$from, $to])
                ->orWhereBetween('close_date', [$from, $to])
                ->orWhereBetween('listing_date', [$from, $to]))
            ->orderBy('name')
            ->get();

        $events = [];
        foreach ($ipos as $ipo) {
            foreach (['open_date' => 'Opens', 'close_date' => 'Closes', 'listing_date' => 'Lists'] as $field => $label) {
                if ($date = $ipo->{$field}) {
                    $events[$date->toDateString()][] = ['ipo' => $ipo, 'label' => $label, 'kind' => $field];
                }
            }
        }

        $weeks = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $weeks[intdiv($gridStart->diffInDays($d), 7)][] = [
                'date' => $d->copy(),
                'inMonth' => $d->month === $month->month,
                'today' => $d->isToday(),
                'events' => $events[$d->toDateString()] ?? [],
            ];
        }

        $agenda = collect($events)
            ->filter(fn ($_, $date) => str_starts_with($date, $month->format('Y-m')))
            ->sortKeys();

        return view('ipos.calendar', [
            'month' => $month,
            'weeks' => $weeks,
            'agenda' => $agenda,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
            'ipoCount' => $ipos->count(),
        ]);
    }

    /** Instant search suggestions for the header search box. */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = Ipo::search($q)
            ->orderByRaw('open_date is null')->orderByDesc('open_date')
            ->limit(8)->get()
            ->map(fn (Ipo $i) => [
                'name' => $i->name,
                'slug' => $i->slug,
                'url' => $i->url(),
                'type' => $i->typeLabel(),
                'status' => $i->statusLabel(),
                'statusKey' => $i->status(),
                'meta' => $i->open_date ? $i->open_date->format('j M Y') : 'Dates awaited',
            ]);

        return response()->json($results);
    }
}
