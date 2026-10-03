<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsReport;
use App\Services\Analytics\AnalyticsRollup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Site analytics in the admin: views and visitors per page, sources, devices and actions.
 */
class AnalyticsController extends Controller
{
    public const RANGES = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'month' => 'This month',
        'last-month' => 'Last month',
    ];

    public function index(Request $request, AnalyticsReport $report): View
    {
        [$from, $to, $range] = $this->range($request);
        [$prevFrom, $prevTo] = $this->previous($from, $to);
        $section = (string) $request->query('section', '');
        $search = trim((string) $request->query('q', ''));

        // Pages: the full ranking, filtered by section or search, with titles from the page register.
        $pages = collect($report->top('page', $from, $to, 5000));
        $meta = $report->pages($pages->pluck('value')->all());
        $pages = $pages->map(fn (array $row): array => $row + [
            'title' => $meta[$row['value']]->title ?? null,
            'section' => $meta[$row['value']]->section ?? 'Other pages',
        ]);
        $sections = $pages->pluck('section')->unique()->sort()->values()->all();
        $filtered = $pages
            ->when($section !== '', fn ($c) => $c->where('section', $section))
            ->when($search !== '', fn ($c) => $c->filter(fn (array $row): bool => str_contains(strtolower($row['value'].' '.$row['title']), strtolower($search))))
            ->values();

        return view('admin.analytics.index', [
            'range' => $range, 'from' => $from, 'to' => $to, 'section' => $section, 'search' => $search, 'sections' => $sections,
            'totals' => $report->totals($from, $to),
            'previous' => $report->totals($prevFrom, $prevTo),
            'live' => $report->liveVisitors(),
            'series' => $report->series($from, $to),
            'pages' => $filtered->take($request->boolean('all') ? 1000 : 50)->all(),
            'pageCount' => $filtered->count(),
            'notFound' => $pages->where('section', 'Not found')->take(10)->values()->all(),
            'lists' => [
                'Sources' => $report->top('source', $from, $to, 12),
                'Sections' => $report->top('section', $from, $to, 12),
                'Landing pages' => $this->titled($report, $report->top('entry', $from, $to, 10)),
                'Devices' => $report->top('device', $from, $to, 5),
                'Browsers' => $report->top('browser', $from, $to, 8),
                'Operating systems' => $report->top('os', $from, $to, 6),
                'Campaigns (UTM)' => $report->top('campaign', $from, $to, 10),
            ],
            'events' => $report->events($from, $to),
            'recent' => $report->recent(15),
            'retention' => AnalyticsRollup::RETENTION_DAYS,
        ]);
    }

    public function page(Request $request, AnalyticsReport $report): View
    {
        $path = (string) $request->query('path', '/');
        abort_unless(str_starts_with($path, '/'), 404);
        [$from, $to, $range] = $this->range($request);
        [$prevFrom, $prevTo] = $this->previous($from, $to);

        return view('admin.analytics.page', [
            'path' => $path, 'range' => $range, 'from' => $from, 'to' => $to,
            'meta' => $report->pages([$path])[$path] ?? null,
            'totals' => $report->totals($from, $to, $path),
            'previous' => $report->totals($prevFrom, $prevTo, $path),
            'series' => $report->series($from, $to, $path),
            'lists' => [
                'Sources' => $report->top('source', $from, $to, 12, $path),
                'Devices' => $report->top('device', $from, $to, 5, $path),
                'Browsers' => $report->top('browser', $from, $to, 8, $path),
                'Campaigns (UTM)' => $report->top('campaign', $from, $to, 10, $path),
            ],
            'entries' => $report->totals($from, $to, $path)['views'] ? collect($report->top('entry', $from, $to, 1, $path))->sum('views') : 0,
            'retention' => AnalyticsRollup::RETENTION_DAYS,
        ]);
    }

    public function export(Request $request, AnalyticsReport $report): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $pages = $report->top('page', $from, $to, 100000);
        $meta = $report->pages(array_column($pages, 'value'));
        $site = rtrim((string) config('app.url'), '/');

        return response()->streamDownload(function () use ($pages, $meta, $site): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Page', 'Title', 'Section', 'URL', 'Views', 'Visitors (daily unique)']);
            foreach ($pages as $row) {
                fputcsv($out, [$row['value'], $meta[$row['value']]->title ?? '', $meta[$row['value']]->section ?? '', $site.$row['value'], $row['views'], $row['visitors']]);
            }
            fclose($out);
        }, 'page-views-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function range(Request $request): array
    {
        $range = (string) $request->query('range', '7d');
        $today = now()->startOfDay();

        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse((string) $request->query('from'))->startOfDay();
                $to = Carbon::parse((string) $request->query('to'))->startOfDay()->min($today);

                return $from->lte($to) && $from->diffInDays($to) <= 400 ? [$from, $to, 'custom'] : [$today->copy()->subDays(6), $today, '7d'];
            } catch (\Throwable) {
                // Fall through to the preset ranges.
            }
        }

        return match ($range) {
            'today' => [$today->copy(), $today->copy(), 'today'],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay(), 'yesterday'],
            '30d' => [$today->copy()->subDays(29), $today->copy(), '30d'],
            '90d' => [$today->copy()->subDays(89), $today->copy(), '90d'],
            'month' => [$today->copy()->startOfMonth(), $today->copy(), 'month'],
            'last-month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(), 'last-month'],
            default => [$today->copy()->subDays(6), $today->copy(), '7d'],
        };
    }

    /**
     * The same number of days just before the range, for the change figures.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previous(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;

        return [$from->copy()->subDays($days), $from->copy()->subDay()];
    }

    /**
     * @param  list<array{value: string, views: int, visitors: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private function titled(AnalyticsReport $report, array $rows): array
    {
        $meta = $report->pages(array_column($rows, 'value'));

        return array_map(fn (array $row): array => $row + ['title' => $meta[$row['value']]->title ?? null, 'path' => $row['value']], $rows);
    }
}
