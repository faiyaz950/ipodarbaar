<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Models\IpoVote;
use App\Services\IpoDigestBuilder;
use App\Services\NewsService;
use App\Support\IpoHubs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

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
        if ($type !== null && array_key_exists((string) $request->query('status'), Ipo::STATUSES)) {
            $filter = $status = $request->query('status');
        }
        if ($type === null && in_array($request->query('type'), ['mainboard', 'sme'], true)) {
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

    public function show(Request $request, Ipo $ipo, NewsService $news)
    {
        $voterId = (string) $request->cookie(IpoVote::COOKIE);
        if (! Str::isUuid($voterId)) {
            $voterId = (string) Str::uuid();
            Cookie::queue(IpoVote::COOKIE, $voterId, 60 * 24 * 365);
        }

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
            'myVote' => $ipo->votes()->where('voter_hash', IpoVote::hash($voterId))->value('choice'),
            'related' => $related,
            'ipoNews' => $companyNews ?: $news->latest(5, 1, 9)['items'],
            'companyNews' => $companyNews !== [],
        ]);
    }

    public function gmp(IpoDigestBuilder $digest)
    {
        $active = Ipo::active()->get()->sortBy(fn (Ipo $i) => [
            $i->hasGmp() ? 0 : 1,
            ['open' => 0, 'upcoming' => 1, 'closed' => 2][$i->status()] ?? 3,
            $i->close_date?->timestamp ?? PHP_INT_MAX,
        ])->values();

        $recent = Ipo::listed()->whereNotNull('listing_date')
            ->whereDate('listing_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('listing_date')->get();

        $allotmentDates = $active->mapWithKeys(fn (Ipo $ipo): array => [$ipo->id => $digest->allotmentDate($ipo)]);

        return view('ipos.gmp', compact('active', 'recent', 'allotmentDates'));
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
