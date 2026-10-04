@extends('layouts.admin')

@php
    $change = function (int $now, int $before): ?array {
        if ($before === 0) {
            return null;
        }
        $pct = ($now - $before) / $before * 100;

        return ['text' => ($pct >= 0 ? '+' : '').number_format($pct, 0).'%', 'cls' => $pct >= 0 ? 'up' : 'down'];
    };
    $rangeLabel = $range === 'custom' ? $from->format('j M').' – '.$to->format('j M Y') : \App\Http\Controllers\Admin\AnalyticsController::RANGES[$range];
    $site = rtrim(config('app.url'), '/');
    $pageMax = max(1, collect($pages)->max('views'));
@endphp

@section('title', 'Analytics')

@section('content')
@include('admin.analytics._styles')

<div class="admin-head">
    <div>
        <h1>Analytics</h1>
        <p class="muted">Page views and visitors on {{ parse_url($site, PHP_URL_HOST) }}, counted without cookies. Visits from browsers that have opened this admin panel aren't counted.</p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-outline btn-sm" href="{{ route('admin.analytics.export', request()->only('range', 'from', 'to')) }}"><x-icon name="download" :size="15" /> Export pages (CSV)</a>
    </div>
</div>

@include('admin.analytics._range', ['params' => array_filter(['section' => $section, 'q' => $search])])

<div class="admin-stats">
    @php $c = $change($totals['views'], $previous['views']); @endphp
    <div class="card card-pad"><span class="muted">Page views · {{ $rangeLabel }}</span><b class="kpi">{{ number_format($totals['views']) }}@if ($c)<span class="an-delta {{ $c['cls'] }}">{{ $c['text'] }}</span>@endif</b></div>
    @php $c = $change($totals['visitors'], $previous['visitors']); @endphp
    <div class="card card-pad"><span class="muted">Visitors (unique per day)</span><b class="kpi">{{ number_format($totals['visitors']) }}@if ($c)<span class="an-delta {{ $c['cls'] }}">{{ $c['text'] }}</span>@endif</b></div>
    <div class="card card-pad"><span class="muted">Pages per visitor</span><b class="kpi">{{ $totals['visitors'] ? number_format($totals['views'] / $totals['visitors'], 1) : '—' }}</b></div>
    <div class="card card-pad"><span class="muted an-live"><i></i> On the site now</span><b class="kpi">{{ number_format($live) }}</b><small class="muted">visitors in the last 5 minutes</small></div>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-head">
        <div class="card-title">Page views, {{ strtolower($rangeLabel) }}</div>
        <div class="card-sub">{{ count($series) === 24 ? 'By hour' : 'By day' }} · change compared with the previous {{ $from->diffInDays($to) + 1 }} {{ \Illuminate\Support\Str::plural('day', $from->diffInDays($to) + 1) }}</div>
    </div>
    @include('admin.analytics._chart', ['series' => $series, 'title' => 'Page views '.strtolower($rangeLabel)])
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-head">
        <div class="card-title">Pages <span class="muted" style="font-weight:500;font-size:13px">{{ number_format($pageCount) }} {{ \Illuminate\Support\Str::plural('page', $pageCount) }}</span></div>
        <form method="get" action="{{ route('admin.analytics') }}" style="display:flex;gap:8px;align-items:center">
            @foreach (request()->only('range', 'from', 'to') as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
            <select class="input" name="section" style="height:32px" onchange="this.form.submit()">
                <option value="">All sections</option>
                @foreach ($sections as $name)<option value="{{ $name }}" @selected($section === $name)>{{ $name }}</option>@endforeach
            </select>
            <input class="input" type="search" name="q" value="{{ $search }}" placeholder="Search pages…" style="height:32px;width:180px">
        </form>
    </div>
    @if (count($pages))
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th style="width:36px">#</th><th>Page</th><th>Section</th><th class="r">Views</th><th class="r">Visitors</th><th class="r">Share</th><th></th></tr></thead>
                <tbody>
                    @foreach ($pages as $row)
                        <tr>
                            <td class="muted">{{ $loop->iteration }}</td>
                            <td style="white-space:normal;min-width:260px">
                                <a href="{{ route('admin.analytics.page', ['path' => $row['value']] + request()->only('range', 'from', 'to')) }}"><b>{{ $row['title'] ?: $row['value'] }}</b></a>
                                <small class="an-path muted" style="display:block">{{ $row['value'] }}</small>
                            </td>
                            <td><span class="badge b-plain">{{ $row['section'] }}</span></td>
                            <td class="r"><b>{{ number_format($row['views']) }}</b></td>
                            <td class="r">{{ number_format($row['visitors']) }}</td>
                            <td class="r" style="min-width:110px">
                                {{ $totals['views'] ? number_format($row['views'] / $totals['views'] * 100, 1) : 0 }}%
                                <div class="an-share"><i style="width: {{ round($row['views'] / $pageMax * 100, 1) }}%"></i></div>
                            </td>
                            <td class="r"><a class="btn btn-outline btn-sm" href="{{ $site.$row['value'] }}" target="_blank" rel="noopener" title="Open the page"><x-icon name="external" :size="14" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($pageCount > count($pages))
            <p style="padding:12px 18px;margin:0"><a class="link" href="{{ route('admin.analytics', request()->query() + ['all' => 1]) }}">Show all {{ number_format($pageCount) }} pages</a></p>
        @endif
    @else
        <p class="muted" style="padding:14px 18px;margin:0;font-size:13.5px">No page views in this range yet. Views are counted from the moment this feature went live.</p>
    @endif
</div>

<div class="admin-grid" style="margin-bottom:20px">
    @include('admin.analytics._list', ['title' => 'Traffic sources', 'rows' => $lists['Sources'], 'column' => 'Source'])
    @include('admin.analytics._list', ['title' => 'Sections', 'rows' => $lists['Sections'], 'column' => 'Section'])
    @include('admin.analytics._list', ['title' => 'Landing pages (first page of a visit)', 'rows' => $lists['Landing pages'], 'column' => 'Page'])
    @include('admin.analytics._list', ['title' => 'Devices', 'rows' => $lists['Devices'], 'column' => 'Device'])
    @include('admin.analytics._list', ['title' => 'Browsers', 'rows' => $lists['Browsers'], 'column' => 'Browser'])
    @include('admin.analytics._list', ['title' => 'Operating systems', 'rows' => $lists['Operating systems'], 'column' => 'System'])
</div>

<div class="admin-grid" style="margin-bottom:20px">
    <div class="card" style="grid-column: span 2">
        <div class="card-head"><div class="card-title">Actions</div><div class="card-sub">Clicks and actions by visitors</div></div>
        @if (count($events))
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Action</th><th class="r">Times</th><th class="r">Visitors</th><th>Most common</th></tr></thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td><b>{{ \Illuminate\Support\Str::headline($event['name']) }}</b></td>
                                <td class="r">{{ number_format($event['count']) }}</td>
                                <td class="r">{{ number_format($event['visitors']) }}</td>
                                <td style="white-space:normal" class="muted">{{ $event['labels'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted" style="padding:14px 18px;margin:0;font-size:13.5px">No actions yet: shares, watchlist adds, poll votes, subscriptions and similar clicks will appear here.</p>
        @endif
    </div>
    @include('admin.analytics._list', ['title' => 'Campaigns (UTM)', 'rows' => $lists['Campaigns (UTM)'], 'column' => 'Campaign'])
</div>

<div class="admin-grid">
    <div class="card" style="grid-column: span 2">
        <div class="card-head"><div class="card-title">Latest page views</div><div class="card-sub">Live feed</div></div>
        @if ($recent->isNotEmpty())
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Time</th><th>Page</th><th>Source</th><th>Device</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $view)
                            <tr>
                                <td class="muted">{{ \Illuminate\Support\Carbon::parse($view->created_at)->diffForHumans(short: true) }}</td>
                                <td style="white-space:normal"><a href="{{ $site.$view->path }}" target="_blank" rel="noopener">{{ $view->title ?: $view->path }}</a></td>
                                <td>{{ $view->source }}</td>
                                <td>{{ $view->device }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted" style="padding:14px 18px;margin:0;font-size:13.5px">Waiting for the first visit.</p>
        @endif
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">Broken links (404)</div><div class="card-sub">Pages visitors reached that don't exist</div></div>
        @forelse ($notFound as $row)
            <div class="an-bar-row" style="grid-template-columns:minmax(0,1fr) 60px"><div class="lbl"><b class="an-path">{{ $row['value'] }}</b></div><span class="num">{{ number_format($row['views']) }}</span></div>
        @empty
            <p class="muted" style="padding:14px 18px;margin:0;font-size:13.5px">None in this range.</p>
        @endforelse
    </div>
</div>

<p class="muted" style="font-size:12.5px;margin-top:18px">A visitor is counted once per day per device (a daily hash of the IP address and browser, which is never stored). Bots and repeat reloads within 30 seconds aren't counted. Detailed rows are kept for {{ $retention }} days; daily totals (added up each night) are kept for good.</p>
@endsection
