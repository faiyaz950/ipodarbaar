@extends('layouts.app')

@php
    use App\Support\IpoHubs;

    $pageNo = $ipos->currentPage();
    $heading = $q !== '' ? 'Search results for “'.$q.'”' : 'IPO List '.now()->year.': All IPOs in India';
@endphp

@section('title', $q !== '' ? $heading : 'IPO List '.now()->year.': All Mainboard & SME IPOs in India'.($pageNo > 1 ? ' – Page '.$pageNo : ''))
@section('robots', $q !== '' ? 'noindex, follow' : 'index, follow')
@section('description', 'Complete IPO list for India: '.number_format($counts['all']).' mainboard and SME IPOs with price band, issue size, GMP, subscription dates and listing dates. Updated daily.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO List', route('ipos.index')]]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO List</span></nav>
        <h1>{{ $heading }}</h1>
        <p class="lead">Every mainboard and SME IPO in one list, newest first. Jump to a status below or search by company name.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="filterbar">
                <div class="chips">
                    <a class="chip active" href="{{ route('ipos.index') }}">All <span class="count">{{ number_format($counts['all']) }}</span></a>
                    @foreach (\App\Models\Ipo::STATUSES as $key => $label)
                        <a class="chip" href="{{ IpoHubs::urlForStatus($key) }}">
                            <span class="cdot" style="background: var(--{{ ['open' => 'green', 'upcoming' => 'blue', 'closed' => 'amber', 'listed' => 'muted'][$key] }})"></span>
                            {{ $label }} <span class="count">{{ number_format($counts[$key]) }}</span>
                        </a>
                    @endforeach
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
                    <div class="seg">
                        <a href="{{ route('ipos.index') }}" class="active">All</a>
                        <a href="{{ route('ipos.mainboard') }}">Mainboard</a>
                        <a href="{{ route('ipos.sme') }}">SME</a>
                    </div>
                    <form method="get" action="{{ route('ipos.index') }}" class="input-icon" role="search">
                        <x-icon name="search" :size="16" />
                        <input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search company…" aria-label="Search IPOs">
                    </form>
                </div>
            </div>

            <x-ipo-table :ipos="$ipos" :empty="$q !== '' ? 'No IPOs match your search.' : 'No IPOs in this category right now.'" />

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
