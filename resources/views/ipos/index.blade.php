@extends('layouts.app')

@php
    $base = $type ? $title : 'IPOs';
    $heading = $q !== '' ? 'Search results for “'.$q.'”' : match ($status) {
        'open' => 'Open '.$base,
        'upcoming' => 'Upcoming '.$base,
        'closed' => $base.' Listing Soon',
        'listed' => 'Listed '.$base,
        default => $title,
    };
    $baseRoute = fn (array $params = []) => $type
        ? route('ipos.type', array_merge(['type' => $type], $params))
        : route('ipos.index', $params);
    $keep = array_filter(['q' => $q ?: null]);
@endphp

@section('title', $heading)
@section('robots', $q !== '' ? 'noindex, follow' : 'index, follow')
@section('description', 'Complete list of '.strtolower($title).' in India with price band, issue size, GMP, subscription dates and listing dates. Updated daily.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('ipos.index') }}">IPOs</a>
            @if ($type)<x-icon name="chevron-right" :size="13" /> <span>{{ $title }}</span>@endif
        </nav>
        <h1>{{ $heading }}</h1>
        <p class="lead">
            @if ($type === 'sme')
                SME IPOs listed on NSE Emerge and BSE SME platforms: price band, issue size, GMP and key dates.
            @elseif ($type === 'mainboard')
                Mainboard IPOs listing on NSE and BSE: price band, issue size, GMP and key dates.
            @else
                Every mainboard and SME IPO in one list. Filter by status, board or search by company name.
            @endif
        </p>
    </div>
</section>

<div class="page-body">
    <div class="container">
        <div class="card">
            <div class="filterbar">
                <div class="chips">
                    <a class="chip {{ ! $status ? 'active' : '' }}" href="{{ $baseRoute($keep) }}">All <span class="count">{{ number_format($counts['all']) }}</span></a>
                    @foreach (\App\Models\Ipo::STATUSES as $key => $label)
                        <a class="chip {{ $status === $key ? 'active' : '' }}" href="{{ $baseRoute(array_merge($keep, ['status' => $key])) }}">
                            <span class="cdot" style="background: var(--{{ ['open' => 'green', 'upcoming' => 'blue', 'closed' => 'amber', 'listed' => 'muted'][$key] }})"></span>
                            {{ $label }} <span class="count">{{ number_format($counts[$key]) }}</span>
                        </a>
                    @endforeach
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
                    <div class="seg">
                        <a href="{{ route('ipos.index', array_filter(['status' => $status, 'q' => $q ?: null])) }}" class="{{ ! $type ? 'active' : '' }}">All</a>
                        <a href="{{ route('ipos.type', array_filter(['type' => 'mainboard', 'status' => $status, 'q' => $q ?: null])) }}" class="{{ $type === 'mainboard' ? 'active' : '' }}">Mainboard</a>
                        <a href="{{ route('ipos.type', array_filter(['type' => 'sme', 'status' => $status, 'q' => $q ?: null])) }}" class="{{ $type === 'sme' ? 'active' : '' }}">SME</a>
                    </div>
                    <form method="get" action="{{ $baseRoute() }}" class="input-icon">
                        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                        <x-icon name="search" :size="16" />
                        <input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search company…">
                    </form>
                </div>
            </div>

            <x-ipo-table :ipos="$ipos" :status="$status" :empty="$q !== '' ? 'No IPOs match your search.' : 'No IPOs in this category right now.'" />

            @if ($ipos->hasPages())
                <x-ad-slot name="list" style="margin:16px 20px 0" />
                <div style="border-top: 1px solid var(--border)">{{ $ipos->links() }}</div>
            @endif
        </div>

        <div class="note info" style="margin-top:20px">
            <x-icon name="info" />
            <span>Showing {{ $ipos->firstItem() ?? 0 }}–{{ $ipos->lastItem() ?? 0 }} of {{ number_format($ipos->total()) }} IPOs. GMP (Grey Market Premium) is unofficial and indicative only. Allotment and listing dates after the issue closes follow SEBI's T+3 timeline and may change.</span>
        </div>
    </div>
</div>
@endsection
