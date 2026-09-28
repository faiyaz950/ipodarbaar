@extends('layouts.app')

@php
    use App\Models\Ipo;
    use Illuminate\Support\Carbon;

    $maxMonth = max(1, ...array_column($months, 'count'));
    $faqs = [
        ['What is an SME IPO?', 'An SME IPO is a public issue by a small or medium enterprise that lists on NSE Emerge or BSE SME. The offer document is reviewed by the stock exchange, and after listing a market maker quotes prices for three years.'],
        ['What is the minimum investment in an SME IPO?', 'Individual investors must apply for at least two lots, which usually works out to more than ₹2 lakh. Use the SME IPO minimum investment calculator for any issue.'],
        ['How is the average SME GMP worked out?', 'It is the simple average of the grey market premium, as a percentage of the upper price band, of every open, upcoming and closed SME IPO that has a GMP today. GMP is unofficial and indicative only.'],
        ['Are SME IPOs riskier than mainboard IPOs?', 'Generally yes. SME companies are smaller, disclosure is lighter, shares trade in lots after listing and liquidity is often thin, so prices can swing sharply both ways.'],
    ];
@endphp

@section('title', 'SME IPO Dashboard '.$year.': GMP, Open & Upcoming SME IPOs')
@section('description', 'SME IPO dashboard for '.$year.': '.$open->count().' open and '.$upcoming->count().' upcoming SME IPOs, top SME GMP today, funds raised and month-wise SME IPO count.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['SME IPOs', route('ipos.sme')], ['SME IPO Dashboard', route('ipos.sme-dashboard')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'SME IPO Dashboard '.$year,
    'url' => route('ipos.sme-dashboard'),
    'dateModified' => now()->toIso8601String(),
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $open->count() + $upcoming->count(),
        'itemListElement' => $open->concat($upcoming)->values()->map(fn (Ipo $ipo, int $i): array => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => $ipo->url(),
            'name' => $ipo->name.' IPO',
        ])->all(),
    ],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('ipos.sme') }}">SME IPOs</a> <x-icon name="chevron-right" :size="13" />
            <span>Dashboard</span>
        </nav>
        <h1>SME IPO Dashboard {{ $year }}</h1>
        <p class="lead">The SME IPO market at a glance: issues open and coming up, the highest grey market premiums and this year's numbers for NSE Emerge and BSE SME.</p>
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Updated {{ now()->format('j M Y, g:i A') }} IST</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="facts-grid cols-3">
                <a class="fact" href="{{ route('ipos.current', ['type' => 'sme']) }}"><span><x-icon name="zap" :size="14" /> Open now</span><b>{{ $open->count() }}</b></a>
                <a class="fact" href="{{ route('ipos.upcoming-sme') }}"><span><x-icon name="calendar" :size="14" /> Upcoming</span><b>{{ $upcoming->count() }}</b></a>
                <a class="fact" href="{{ route('ipos.listing-today') }}"><span><x-icon name="rocket" :size="14" /> Listing this week</span><b>{{ $listingWeek }}</b></a>
                <a class="fact" href="{{ route('ipos.gmp.sme') }}"><span><x-icon name="trending-up" :size="14" /> Average SME GMP</span><b class="{{ $avgGmp === null ? '' : ($avgGmp > 0 ? 'up' : ($avgGmp < 0 ? 'down' : '')) }}">{{ $avgGmp === null ? '—' : ($avgGmp > 0 ? '+' : '').number_format($avgGmp, 1).'%' }}</b></a>
                <div class="fact"><span><x-icon name="layers" :size="14" /> SME IPOs in {{ $year }}</span><b>{{ number_format($yearCount) }}@if ($yearShare !== null) <small>{{ $yearShare }}% of all IPOs</small>@endif</b></div>
                <div class="fact"><span><x-icon name="coins" :size="14" /> Raised in {{ $year }}</span><b>{{ $yearRaised ? '₹'.Ipo::num($yearRaised, 0).' Cr' : '—' }}</b></div>
            </div>
        </div>

        @if ($topGmp->isNotEmpty())
            <div class="card card-pad">
                <h2 class="card-title" id="top-gmp" style="margin-bottom:10px"><span class="ico"><x-icon name="trending-up" :size="16" /></span> Highest SME IPO GMP Today</h2>
                <ol class="rank-list">
                    @foreach ($topGmp as $ipo)
                        <li>
                            <a href="{{ $ipo->url() }}#gmp">{{ $ipo->name }} IPO</a>:
                            GMP <b class="up">+₹{{ Ipo::num($ipo->gmp) }}</b> ({{ number_format($ipo->gmpPercent(), 1) }}%), expected listing ₹{{ Ipo::num($ipo->estListingPrice()) }}
                            <span class="muted">· {{ match ($ipo->status()) { 'open' => 'closes '.($ipo->close_date ?? $ipo->open_date)->format('j M'), 'upcoming' => $ipo->open_date ? 'opens '.$ipo->open_date->format('j M') : 'dates awaited', default => 'lists '.($ipo->listing_date?->format('j M') ?? 'TBA') } }}</span>
                        </li>
                    @endforeach
                </ol>
                <a class="link-gold" href="{{ route('ipos.gmp.sme') }}" style="display:inline-flex;margin-top:12px">Live GMP of all SME IPOs <x-icon name="arrow-right" :size="14" /></a>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="open"><span class="ico"><x-icon name="zap" :size="16" /></span> SME IPOs Open Now</h2>
                    <div class="card-sub">Sorted by closing date</div>
                </div>
                <a class="link-gold" href="{{ route('ipos.current', ['type' => 'sme']) }}">All open <x-icon name="arrow-right" :size="14" /></a>
            </div>
            <x-ipo-table :ipos="$open->take(10)" status="open" empty="No SME IPO is open for subscription right now." />
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="upcoming"><span class="ico"><x-icon name="calendar" :size="16" /></span> Upcoming SME IPOs</h2>
                    <div class="card-sub">Sorted by opening date</div>
                </div>
                <a class="link-gold" href="{{ route('ipos.upcoming-sme') }}">All upcoming <x-icon name="arrow-right" :size="14" /></a>
            </div>
            <x-ipo-table :ipos="$upcoming->take(10)" status="upcoming" empty="No upcoming SME IPO has been announced yet." />
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="months"><span class="ico"><x-icon name="bar-chart" :size="16" /></span> SME IPOs per Month, {{ $year }}</h2>
                        <div class="card-sub">By opening date</div>
                    </div>
                </div>
                <div class="fin-chart month-chart" role="img" aria-label="Number of SME IPOs opening each month of {{ $year }}">
                    @foreach ($months as $month => $data)
                        <div class="fin-col" title="{{ $data['count'] }} SME IPOs · ₹{{ Ipo::num($data['raised'], 0) }} Cr">
                            <div class="fin-bars">
                                <b class="fin-val">{{ $data['count'] ?: '' }}</b>
                                <i class="rev" style="height: {{ round($data['count'] / $maxMonth * 100, 1) }}%"></i>
                            </div>
                            <span class="fin-label">{{ Carbon::create($year, $month)->format('M') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="largest"><span class="ico"><x-icon name="trophy" :size="16" /></span> Largest SME IPOs of {{ $year }}</h2>
                        <div class="card-sub">By issue size</div>
                    </div>
                </div>
                @forelse ($largest as $ipo)
                    <a class="admin-row" href="{{ $ipo->url() }}" style="padding:12px 20px">
                        <span>{{ $ipo->name }} <small class="muted">{{ $ipo->open_date->format('M Y') }}</small></span>
                        <b>₹{{ Ipo::num($ipo->issue_size) }} Cr</b>
                    </a>
                @empty
                    <div class="table-empty">No SME IPOs with a known issue size this year yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card card-pad seo-copy">
            <div>
                <h2>Using the SME IPO dashboard</h2>
                <p>Start with the open and upcoming SME IPOs, then check the grey market premium on the <a class="link" href="{{ route('ipos.gmp.sme') }}">SME IPO GMP</a> page and each company's lot size, financials and objects on its IPO page. Work out the money you need with the <a class="link" href="{{ route('calculators.show', 'sme-ipo-investment') }}">SME IPO minimum investment calculator</a>, and read how SME issues differ from mainboard ones in our <a class="link" href="{{ route('guides.show', 'sme-ipo-vs-mainboard-ipo') }}">SME vs mainboard IPO guide</a>.</p>
            </div>
        </div>

        <x-faq :faqs="$faqs" title="SME IPOs: FAQs" />

        <nav class="card card-pad" aria-label="More SME IPO pages">
            <h2 class="card-title" style="margin-bottom:12px">More on SME IPOs</h2>
            <div class="hub-links">
                <a class="chip" href="{{ route('ipos.sme') }}">SME IPO List {{ $year }}</a>
                <a class="chip" href="{{ route('ipos.upcoming-sme') }}">Upcoming SME IPOs</a>
                <a class="chip" href="{{ route('ipos.gmp.sme') }}">SME IPO GMP</a>
                <a class="chip" href="{{ route('ipos.listing-today') }}">IPO Listing Today</a>
                <a class="chip" href="{{ route('ipos.year', $year) }}">IPO Report Card {{ $year }}</a>
            </div>
        </nav>

        <div class="note info">
            <x-icon name="info" />
            <span>GMP is unofficial and indicative only. Figures are based on the IPOs tracked by IPO Darbaar. Nothing here is investment advice.</span>
        </div>
    </div>
</div>
@endsection
