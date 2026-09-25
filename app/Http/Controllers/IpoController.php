<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\NewsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IpoController extends Controller
{
    public function index(Request $request, ?string $type = null)
    {
        $type = in_array($type, ['mainboard', 'sme'], true) ? $type : $request->query('type');
        $type = in_array($type, ['mainboard', 'sme'], true) ? $type : null;
        $status = array_key_exists((string) $request->query('status'), Ipo::STATUSES) ? $request->query('status') : null;
        $q = trim((string) $request->query('q', ''));

        $ipos = Ipo::query()
            ->ofType($type)
            ->search($q)
            ->inStatus($status)
            ->paginate(25)
            ->withQueryString();

        $counts = [];
        foreach (array_keys(Ipo::STATUSES) as $s) {
            $counts[$s] = Ipo::query()->ofType($type)->search($q)->{$s}()->count();
        }
        $counts['all'] = Ipo::query()->ofType($type)->search($q)->count();

        $title = match ($type) {
            'mainboard' => 'Mainboard IPOs',
            'sme' => 'SME IPOs',
            default => 'All IPOs',
        };

        return view('ipos.index', compact('ipos', 'type', 'status', 'q', 'counts', 'title'));
    }

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

        return view('ipos.show', [
            'ipo' => $ipo,
            'gmpTrend' => $ipo->gmpHistory()->get(['date', 'gmp']),
            'related' => $related,
            'ipoNews' => $news->latest(5, 1, 9)['items'],
        ]);
    }

    public function gmp()
    {
        $active = Ipo::active()->get()->sortBy(fn (Ipo $i) => [
            $i->hasGmp() ? 0 : 1,
            ['open' => 0, 'upcoming' => 1, 'closed' => 2][$i->status()] ?? 3,
            $i->close_date?->timestamp ?? PHP_INT_MAX,
        ])->values();

        $recent = Ipo::listed()->whereNotNull('listing_date')
            ->whereDate('listing_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('listing_date')->get();

        return view('ipos.gmp', compact('active', 'recent'));
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
                'url' => $i->url(),
                'type' => $i->typeLabel(),
                'status' => $i->statusLabel(),
                'statusKey' => $i->status(),
                'meta' => $i->open_date ? $i->open_date->format('j M Y') : 'Dates awaited',
            ]);

        return response()->json($results);
    }
}
