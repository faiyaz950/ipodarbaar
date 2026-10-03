@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="admin-head">
    <div>
        <h1>Dashboard</h1>
        <p class="muted">
            @if ($lastSynced)
                IPO data last synced {{ $lastSynced->diffForHumans() }} ({{ $lastSynced->timezone(config('app.timezone'))->format('j M, g:i a') }}).
            @else
                IPO data has not been synced yet.
            @endif
        </p>
    </div>
    <div class="admin-actions">
        <form method="post" action="{{ route('admin.sync') }}">
            @csrf
            <button type="submit" class="btn btn-navy btn-sm"><x-icon name="refresh" :size="15" /> Quick sync</button>
        </form>
        <form method="post" action="{{ route('admin.sync') }}">
            @csrf
            <input type="hidden" name="full" value="1">
            <button type="submit" class="btn btn-outline btn-sm">Full sync</button>
        </form>
    </div>
</div>

<a class="card card-pad" href="{{ route('admin.analytics', ['range' => 'today']) }}" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:16px;font-size:14px">
    <b style="font-size:15px"><x-icon name="bar-chart" :size="16" /> Today on the site</b>
    <span><b>{{ number_format($traffic['views']) }}</b> <span class="muted">page views</span></span>
    <span><b>{{ number_format($traffic['visitors']) }}</b> <span class="muted">visitors</span></span>
    <span><b>{{ number_format($traffic['live']) }}</b> <span class="muted">on the site now</span></span>
    <span class="link-gold" style="margin-left:auto">Open analytics <x-icon name="arrow-right" :size="14" /></span>
</a>

<div class="admin-stats">
    <div class="card card-pad"><span class="muted">Total IPOs</span><b class="kpi">{{ number_format($stats['total']) }}</b></div>
    <div class="card card-pad"><span class="muted">Not yet listed</span><b class="kpi">{{ number_format($stats['active']) }}</b></div>
    <div class="card card-pad"><span class="muted">Open now</span><b class="kpi up">{{ number_format($stats['open']) }}</b></div>
    <div class="card card-pad"><span class="muted">GMP data points</span><b class="kpi">{{ number_format($stats['history']) }}</b></div>
</div>

<div class="admin-grid">
    @foreach ([
        ['title' => 'Missing lot size', 'sub' => 'Active IPOs without a lot size (lot table and min. investment stay hidden)', 'items' => $missingLot],
        ['title' => 'Missing registrar', 'sub' => 'Active IPOs without a registrar (no allotment-status link)', 'items' => $missingRegistrar],
        ['title' => 'Missing listing price', 'sub' => 'Listed in the last 15 days without an actual listing price', 'items' => $missingListing],
    ] as $panel)
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-title">{{ $panel['title'] }} <span class="badge {{ count($panel['items']) ? 'b-closed' : 'b-open' }}">{{ count($panel['items']) }}</span></div>
                    <div class="card-sub">{{ $panel['sub'] }}</div>
                </div>
            </div>
            @forelse ($panel['items']->take(12) as $ipo)
                <a class="admin-row" href="{{ route('admin.ipos.edit', $ipo) }}">
                    <span>
                        <b>{{ $ipo->name }}</b>
                        <small class="muted">{{ $ipo->typeLabel() }} · {{ $ipo->open_date?->format('j M') ?? 'Dates awaited' }}</small>
                    </span>
                    <span class="badge b-{{ $ipo->status() }}">{{ $ipo->statusLabel() }}</span>
                </a>
            @empty
                <div class="table-empty">Nothing to fix here.</div>
            @endforelse
            @if (count($panel['items']) > 12)
                <div class="table-foot">+{{ count($panel['items']) - 12 }} more</div>
            @endif
        </div>
    @endforeach
</div>
@endsection
