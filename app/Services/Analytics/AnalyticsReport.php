<?php

namespace App\Services\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Answers the admin dashboard's questions for a date range: totals, a day-by-day (or hour-by-hour)
 * series, and top lists per dimension. Past days come from the daily roll-up, today from raw rows.
 * "Visitors" are unique per day, so a range adds up each day's unique visitors.
 */
class AnalyticsReport
{
    /** Dimensions the roll-up keeps and the raw column (or expression) each one groups by. */
    public const DIMENSIONS = [
        'page' => 'path',
        'section' => 'section',
        'source' => 'source',
        'device' => 'device',
        'browser' => 'browser',
        'os' => 'os',
        'campaign' => 'utm_campaign',
        'entry' => 'path',
    ];

    public function __construct(private AnalyticsRollup $rollup) {}

    /**
     * @return array{views: int, visitors: int}
     */
    public function totals(Carbon $from, Carbon $to, ?string $path = null): array
    {
        $rows = $this->top('total', $from, $to, 1, $path);

        return ['views' => (int) ($rows[0]['views'] ?? 0), 'visitors' => (int) ($rows[0]['visitors'] ?? 0)];
    }

    /**
     * One point per day, or per hour when the range is a single day.
     *
     * @return list<array{label: string, date: string, views: int, visitors: int}>
     */
    public function series(Carbon $from, Carbon $to, ?string $path = null): array
    {
        if ($from->isSameDay($to)) {
            $rows = $this->raw($from, $to, $path)
                ->selectRaw($this->hourExpression().' as bucket, count(*) as views, count(distinct visitor) as visitors')
                ->groupBy('bucket')->get()->keyBy(fn ($row): int => (int) $row->bucket);

            return array_map(fn (int $hour): array => [
                'label' => Carbon::createFromTime($hour)->format('g A'),
                'date' => $from->format('D, j M').', '.Carbon::createFromTime($hour)->format('g A'),
                'views' => (int) ($rows[$hour]->views ?? 0),
                'visitors' => (int) ($rows[$hour]->visitors ?? 0),
            ], range(0, 23));
        }

        // Site-wide days come from the roll-up; a single page's days from its raw rows. Today is always raw.
        $this->rollup->ensure($from, $to->copy()->min(now()->subDay()));
        $byDay = $path === null
            ? DB::table('analytics_daily')->where('dimension', 'total')->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get(['date', 'views', 'visitors'])->keyBy(fn ($row): string => substr((string) $row->date, 0, 10))
            : collect();
        $rawFrom = $path === null ? now()->startOfDay()->max($from) : $from;
        if ($rawFrom->lte($to)) {
            $raw = $this->raw($rawFrom, $to, $path)->selectRaw('date(created_at) as day, count(*) as views, count(distinct visitor) as visitors')->groupBy('day')->get();
            foreach ($raw as $row) {
                $byDay[substr((string) $row->day, 0, 10)] = $row;
            }
        }

        $points = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $row = $byDay[$day->toDateString()] ?? null;
            $points[] = ['label' => $day->format('j M'), 'date' => $day->format('D, j M Y'), 'views' => (int) ($row->views ?? 0), 'visitors' => (int) ($row->visitors ?? 0)];
        }

        return $points;
    }

    /**
     * The top values of a dimension, most viewed first.
     *
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function top(string $dimension, Carbon $from, Carbon $to, int $limit = 20, ?string $path = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $today = now()->startOfDay();
        $merged = [];

        // Whole days before today: the roll-up (filled in for any day it hasn't covered yet).
        if ($from->lt($today)) {
            $until = $to->lt($today) ? $to->copy()->startOfDay() : $today->copy()->subDay();
            $this->rollup->ensure($from, $until);
            if ($path === null) {
                $rows = DB::table('analytics_daily')->where('dimension', $dimension)
                    ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
                    ->selectRaw('value, sum(views) as views, sum(visitors) as visitors')->groupBy('value')->get();
                foreach ($rows as $row) {
                    $merged[$row->value] = ['views' => (int) $row->views, 'visitors' => (int) $row->visitors];
                }
            } else {
                // A single page's breakdown isn't rolled up; read its raw rows (kept for the retention period).
                foreach ($this->rawTop($dimension, $from, $until->copy()->endOfDay(), $path) as $value => $row) {
                    $merged[$value] = $row;
                }
            }
        }

        // Today: straight from the raw rows.
        if ($to->gte($today)) {
            foreach ($this->rawTop($dimension, $today, $to, $path) as $value => $row) {
                $merged[$value] = [
                    'views' => ($merged[$value]['views'] ?? 0) + $row['views'],
                    'visitors' => ($merged[$value]['visitors'] ?? 0) + $row['visitors'],
                ];
            }
        }

        return collect($merged)
            ->map(fn (array $row, string|int $value): array => ['value' => (string) $value] + $row)
            ->sortByDesc('views')->take($limit)->values()->all();
    }

    /**
     * Events (clicks and actions) in the range, with their most common labels.
     *
     * @return list<array{name: string, count: int, visitors: int, labels: string}>
     */
    public function events(Carbon $from, Carbon $to): array
    {
        $base = DB::table('analytics_events')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        return (clone $base)->selectRaw('name, count(*) as total, count(distinct visitor) as visitors')->groupBy('name')->orderByDesc('total')->get()
            ->map(function ($row) use ($base): array {
                $labels = (clone $base)->where('name', $row->name)->whereNotNull('label')
                    ->selectRaw('label, count(*) as total')->groupBy('label')->orderByDesc('total')->limit(3)->pluck('total', 'label')
                    ->map(fn ($n, $label): string => $label.' ('.$n.')')->implode(', ');

                return ['name' => $row->name, 'count' => (int) $row->total, 'visitors' => (int) $row->visitors, 'labels' => $labels];
            })->all();
    }

    /** People with a page view in the last few minutes. */
    public function liveVisitors(int $minutes = 5): int
    {
        return (int) DB::table('page_views')->where('created_at', '>=', now()->subMinutes($minutes))->distinct()->count('visitor');
    }

    /**
     * @return Collection<int, object>
     */
    public function recent(int $limit = 20): Collection
    {
        return DB::table('page_views')->leftJoin('analytics_pages', 'analytics_pages.path', '=', 'page_views.path')
            ->select('page_views.path', 'page_views.source', 'page_views.device', 'page_views.created_at', 'analytics_pages.title')
            ->orderByDesc('page_views.id')->limit($limit)->get();
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, object>
     */
    public function pages(array $paths): array
    {
        return DB::table('analytics_pages')->whereIn('path', $paths)->get()->keyBy('path')->all();
    }

    /**
     * @return array<string|int, array{views: int, visitors: int}>
     */
    private function rawTop(string $dimension, Carbon $from, Carbon $to, ?string $path): array
    {
        $query = $this->raw($from, $to, $path);
        if ($dimension === 'total') {
            $row = $query->selectRaw('count(*) as views, count(distinct visitor) as visitors')->first();

            return ['' => ['views' => (int) ($row->views ?? 0), 'visitors' => (int) ($row->visitors ?? 0)]];
        }
        if ($dimension === 'entry') {
            $query->where('is_entry', true);
        }
        $column = self::DIMENSIONS[$dimension];
        $query->whereNotNull($column);

        return $query->selectRaw($column.' as value, count(*) as views, count(distinct visitor) as visitors')->groupBy($column)->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->value => ['views' => (int) $row->views, 'visitors' => (int) $row->visitors]])->all();
    }

    private function raw(Carbon $from, Carbon $to, ?string $path): Builder
    {
        return DB::table('page_views')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($path !== null, fn ($q) => $q->where('path', $path));
    }

    private function hourExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "cast(strftime('%H', created_at) as integer)" : 'hour(created_at)';
    }
}
