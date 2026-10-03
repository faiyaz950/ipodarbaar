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
    $url = rtrim(config('app.url'), '/').$path;
@endphp

@section('title', 'Analytics: '.($meta->title ?? $path))

@section('content')
@include('admin.analytics._styles')

<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.analytics', request()->only('range', 'from', 'to')) }}"><x-icon name="arrow-left" :size="14" /> All pages</a>
        <h1>{{ $meta->title ?? $path }}</h1>
        <p class="muted"><span class="badge b-plain">{{ $meta->section ?? 'Page' }}</span> <a class="an-path" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }} <x-icon name="external" :size="12" /></a></p>
    </div>
</div>

@include('admin.analytics._range', ['params' => ['path' => $path]])

<div class="admin-stats">
    @php $c = $change($totals['views'], $previous['views']); @endphp
    <div class="card card-pad"><span class="muted">Page views · {{ $rangeLabel }}</span><b class="kpi">{{ number_format($totals['views']) }}@if ($c)<span class="an-delta {{ $c['cls'] }}">{{ $c['text'] }}</span>@endif</b></div>
    @php $c = $change($totals['visitors'], $previous['visitors']); @endphp
    <div class="card card-pad"><span class="muted">Visitors (unique per day)</span><b class="kpi">{{ number_format($totals['visitors']) }}@if ($c)<span class="an-delta {{ $c['cls'] }}">{{ $c['text'] }}</span>@endif</b></div>
    <div class="card card-pad"><span class="muted">Visits that started here</span><b class="kpi">{{ number_format($entries) }}</b><small class="muted">arrived from Google, links or direct</small></div>
    <div class="card card-pad"><span class="muted">Views per visitor</span><b class="kpi">{{ $totals['visitors'] ? number_format($totals['views'] / $totals['visitors'], 1) : '—' }}</b></div>
</div>

<div class="card" style="margin-bottom:20px">
    <div class="card-head"><div class="card-title">Views of this page, {{ strtolower($rangeLabel) }}</div><div class="card-sub">{{ count($series) === 24 ? 'By hour' : 'By day' }}</div></div>
    @include('admin.analytics._chart', ['series' => $series, 'title' => 'Views of '.$path])
</div>

<div class="admin-grid">
    @include('admin.analytics._list', ['title' => 'Traffic sources', 'rows' => $lists['Sources'], 'column' => 'Source'])
    @include('admin.analytics._list', ['title' => 'Devices', 'rows' => $lists['Devices'], 'column' => 'Device'])
    @include('admin.analytics._list', ['title' => 'Browsers', 'rows' => $lists['Browsers'], 'column' => 'Browser'])
    @include('admin.analytics._list', ['title' => 'Campaigns (UTM)', 'rows' => $lists['Campaigns (UTM)'], 'column' => 'Campaign'])
</div>

<p class="muted" style="font-size:12.5px;margin-top:18px">Per-page detail uses the detailed rows, which are kept for {{ $retention }} days.</p>
@endsection
