<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sums each finished day's raw page views and events into analytics_daily, then removes raw
 * rows past the retention period. Daily totals are kept for good.
 */
class AnalyticsRollup
{
    /** Raw page views and events are kept this many days (per-page detail reports read them). */
    public const RETENTION_DAYS = 90;

    /** Rolls up every finished day from $from to $until that hasn't been rolled up yet. */
    public function ensure(Carbon $from, Carbon $until): void
    {
        $until = $until->copy()->min(now()->subDay())->startOfDay();
        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            if (! Cache::has('analytics:rolled:'.$day->toDateString())) {
                $this->day($day);
            }
        }
    }

    public function day(Carbon $day): void
    {
        $date = $day->toDateString();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $rows = [];

        // Nothing to add once raw rows for the day are gone; keep whatever was rolled up before.
        $raw = DB::table('page_views')->whereBetween('created_at', [$start, $end]);
        $events = DB::table('analytics_events')->whereBetween('created_at', [$start, $end]);
        if (! (clone $raw)->exists() && ! (clone $events)->exists()) {
            Cache::forever('analytics:rolled:'.$date, true);

            return;
        }

        $total = (clone $raw)->selectRaw('count(*) as views, count(distinct visitor) as visitors')->first();
        $rows[] = ['date' => $date, 'dimension' => 'total', 'value' => '', 'views' => (int) $total->views, 'visitors' => (int) $total->visitors];

        foreach (AnalyticsReport::DIMENSIONS as $dimension => $column) {
            $query = (clone $raw)->whereNotNull($column);
            if ($dimension === 'entry') {
                $query->where('is_entry', true);
            }
            foreach ($query->selectRaw($column.' as value, count(*) as views, count(distinct visitor) as visitors')->groupBy($column)->get() as $row) {
                $rows[] = ['date' => $date, 'dimension' => $dimension, 'value' => (string) $row->value, 'views' => (int) $row->views, 'visitors' => (int) $row->visitors];
            }
        }
        foreach ((clone $events)->selectRaw('name as value, count(*) as views, count(distinct visitor) as visitors')->groupBy('name')->get() as $row) {
            $rows[] = ['date' => $date, 'dimension' => 'event', 'value' => (string) $row->value, 'views' => (int) $row->views, 'visitors' => (int) $row->visitors];
        }

        DB::transaction(function () use ($date, $rows): void {
            DB::table('analytics_daily')->where('date', $date)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('analytics_daily')->insert($chunk);
            }
        });
        Cache::forever('analytics:rolled:'.$date, true);
    }

    /** Rolls up yesterday and any missed days, then deletes raw rows older than the retention period. */
    public function nightly(): int
    {
        $first = DB::table('page_views')->min('created_at');
        if ($first) {
            $this->ensure(Carbon::parse($first)->max(now()->subDays(self::RETENTION_DAYS)), now()->subDay());
        }

        $cutoff = now()->subDays(self::RETENTION_DAYS)->startOfDay();
        $deleted = DB::table('page_views')->where('created_at', '<', $cutoff)->delete();
        DB::table('analytics_events')->where('created_at', '<', $cutoff)->delete();

        return $deleted;
    }
}
