@extends('layouts.app')

@php
    use App\Models\Ipo;
    use Illuminate\Support\Collection;
    use Illuminate\Support\HtmlString;

    $date = now()->format('j M Y');
    $next = $thisWeek->first();
    $names = fn (Collection $ipos): string => $ipos->take(4)->map(fn (Ipo $ipo): string => $ipo->name)->join(', ', ' and ').($ipos->count() > 4 ? ' and '.($ipos->count() - 4).' more' : '');
    $summary = $today->isNotEmpty()
        ? $today->count().' '.($today->count() === 1 ? 'IPO lists' : 'IPOs list').' on the stock exchanges today ('.$date.'): '.$names($today).'. Trading starts at 10:00 AM after a special pre-open session.'
        : 'No IPO lists today ('.$date.').'.($next ? ' The next listing is '.$next->name.' on '.$next->listing_date->format('D, j M').'.' : '');
    $withListingPrice = fn (Collection $ipos): bool => $ipos->contains(fn (Ipo $ipo): bool => $ipo->listing_price !== null);
    $gain = fn (Ipo $ipo): ?float => $ipo->listingGainPercent();
    $tone = fn (?float $value): string => $value === null ? 'flat' : ($value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat'));
    $faqs = [
        ['What time do IPO shares start trading on listing day?', 'On listing day the exchanges run a special pre-open session from 9:00 AM to 10:00 AM to discover the opening price. Normal trading in the new shares starts at 10:00 AM.'],
        ['How is the expected listing price calculated?', 'Expected listing price = issue price + latest GMP. It is only an indication from the unofficial grey market; the actual listing price is set by orders placed in the pre-open session.'],
        ['When are IPO shares credited to my demat account?', 'Under SEBI\'s T+3 timeline, allotted shares are credited to demat accounts on T+2, one working day before listing. You can sell them once trading starts on listing day.'],
        ['Where can I see the listing price of an IPO?', 'The listing price appears on NSE and BSE once the pre-open session ends at about 9:55 AM. Each IPO page on IPO Darbaar shows the listing price and listing gain once it is available.'],
        ['Is listing gain taxable?', new HtmlString('Yes. Shares sold within 12 months are taxed as short-term capital gains at 20% plus cess. See our guide to <a href="'.e(route('guides.show', 'ipo-listing-gains-tax')).'">tax on IPO listing gains</a>.')],
    ];
@endphp

@section('title', 'IPO Listing Today ('.$date.'): Expected Listing Price & GMP')
@section('description', ($today->isNotEmpty()
    ? $today->count().' '.($today->count() === 1 ? 'IPO lists' : 'IPOs list').' today ('.$date.'): '.\Illuminate\Support\Str::limit($names($today), 70).'. '
    : 'No IPO lists today ('.$date.'). ').'Issue price, GMP, expected listing price and this week\'s listings.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO', route('ipos.index')], ['IPO Listing Today', route('ipos.listing-today')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'IPO Listing Today',
    'url' => route('ipos.listing-today'),
    'dateModified' => now()->toIso8601String(),
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $today->count() + $thisWeek->count(),
        'itemListElement' => $today->concat($thisWeek)->values()->map(fn (Ipo $ipo, int $i): array => [
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
            <a href="{{ route('ipos.index') }}">IPO</a> <x-icon name="chevron-right" :size="13" />
            <span>Listing Today</span>
        </nav>
        <h1>IPO Listing Today</h1>
        <p class="lead">IPOs listing on NSE and BSE today, with the issue price, latest GMP and expected listing price, plus every listing due this week.</p>
        <p class="hub-summary">{{ $summary }}</p>
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Updated {{ now()->format('j M Y, g:i A') }} IST</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="today"><span class="ico"><x-icon name="rocket" :size="16" /></span> IPOs Listing Today ({{ now()->format('j M') }})</h2>
                    <div class="card-sub">Expected listing price = issue price + GMP. Actual listing price appears after 10 AM.</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table ipo-table">
                    <thead>
                        <tr>
                            <th>IPO</th>
                            <th class="r">Issue price</th>
                            <th class="r">GMP</th>
                            <th class="r">Expected listing</th>
                            <th class="r">Listing price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($today as $ipo)
                            <tr data-type="{{ $ipo->type }}">
                                <td class="company">
                                    <div class="co">
                                        <x-logo-tile :ipo="$ipo" />
                                        <div style="min-width:0">
                                            <span class="co-title"><a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a></span>
                                            <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span> <span>{{ $ipo->exchangeLabel() }}</span>@if ($ipo->issue_size)<span>₹{{ Ipo::num($ipo->issue_size) }} Cr</span>@endif</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="r" data-label="Issue price">{{ $ipo->price ? '₹'.Ipo::num($ipo->price) : '—' }}</td>
                                <td class="r" data-label="GMP"><b class="{{ $tone($ipo->gmp) }}">{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—' }}</b></td>
                                <td class="r" data-label="Expected listing">
                                    {{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}
                                    @if ($ipo->gmpPercent() !== null)<small class="cell-sub {{ $tone($ipo->gmpPercent()) }}">{{ $ipo->gmpPercent() > 0 ? '+' : '' }}{{ number_format($ipo->gmpPercent(), 1) }}%</small>@endif
                                </td>
                                <td class="r" data-label="Listing price">
                                    @if ($ipo->listing_price)
                                        ₹{{ Ipo::num($ipo->listing_price) }}
                                        <small class="cell-sub {{ $tone($gain($ipo)) }}">{{ $gain($ipo) > 0 ? '+' : '' }}{{ number_format($gain($ipo), 1) }}%</small>
                                    @else
                                        <span class="muted">After 10 AM</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="table-empty">{{ $summary }}</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="this-week"><span class="ico"><x-icon name="calendar" :size="16" /></span> Upcoming IPO Listings This Week</h2>
                    <div class="card-sub">IPOs listing in the next 7 days, with GMP-based expected listing price</div>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table ipo-table">
                    <thead>
                        <tr>
                            <th>IPO</th>
                            <th>Listing date</th>
                            <th class="r">Issue price</th>
                            <th class="r">GMP</th>
                            <th class="r">Expected listing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($thisWeek as $ipo)
                            <tr data-type="{{ $ipo->type }}">
                                <td class="company">
                                    <div class="co">
                                        <x-logo-tile :ipo="$ipo" />
                                        <div style="min-width:0">
                                            <span class="co-title"><a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a></span>
                                            <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span> <x-status-badge :ipo="$ipo" /></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Listing date">{{ $ipo->listing_date->format('D, j M') }}</td>
                                <td class="r" data-label="Issue price">{{ $ipo->price ? '₹'.Ipo::num($ipo->price) : '—' }}</td>
                                <td class="r" data-label="GMP"><b class="{{ $tone($ipo->gmp) }}">{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—' }}</b></td>
                                <td class="r" data-label="Expected listing">
                                    {{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}
                                    @if ($ipo->gmpPercent() !== null)<small class="cell-sub {{ $tone($ipo->gmpPercent()) }}">{{ $ipo->gmpPercent() > 0 ? '+' : '' }}{{ number_format($ipo->gmpPercent(), 1) }}%</small>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="table-empty">No other IPO is due to list in the next 7 days.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($recent->isNotEmpty())
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="recent"><span class="ico"><x-icon name="check-circle" :size="16" /></span> Recently Listed IPOs</h2>
                        <div class="card-sub">Listings of the last 10 days{{ $withListingPrice($recent) ? ': listing price and gain against the last GMP' : ' with their last GMP' }}</div>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table ipo-table">
                        <thead>
                            <tr>
                                <th>IPO</th>
                                <th>Listed on</th>
                                <th class="r">Issue price</th>
                                <th class="r">Last GMP</th>
                                @if ($withListingPrice($recent))<th class="r">Listing price</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $ipo)
                                <tr data-type="{{ $ipo->type }}">
                                    <td class="company">
                                        <div class="co">
                                            <x-logo-tile :ipo="$ipo" />
                                            <div style="min-width:0">
                                                <span class="co-title"><a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a></span>
                                                <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Listed on">{{ $ipo->listing_date->format('D, j M') }}</td>
                                    <td class="r" data-label="Issue price">{{ $ipo->price ? '₹'.Ipo::num($ipo->price) : '—' }}</td>
                                    <td class="r" data-label="Last GMP">
                                        <b class="{{ $tone($ipo->gmp) }}">{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—' }}</b>
                                        @if ($ipo->gmpPercent() !== null)<small class="cell-sub">{{ $ipo->gmpPercent() > 0 ? '+' : '' }}{{ number_format($ipo->gmpPercent(), 1) }}% expected</small>@endif
                                    </td>
                                    @if ($withListingPrice($recent))
                                        <td class="r" data-label="Listing price">
                                            @if ($ipo->listing_price)
                                                ₹{{ Ipo::num($ipo->listing_price) }}
                                                <small class="cell-sub {{ $tone($gain($ipo)) }}">{{ $gain($ipo) > 0 ? '+' : '' }}{{ number_format($gain($ipo), 1) }}% actual</small>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card card-pad seo-copy">
            <div>
                <h2>How IPO listing day works</h2>
                <p>An IPO lists on the third working day after it closes (T+3). Between 9:00 and 10:00 AM the exchanges run a special pre-open call auction in which buy and sell orders set the opening price; that price is the listing price. Normal trading starts at 10:00 AM, and allottees can sell from then on because the shares reached their demat accounts the day before.</p>
            </div>
            <div>
                <h2>Expected listing price vs actual listing</h2>
                <p>The expected listing price on this page is the issue price plus the latest grey market premium. GMP is unofficial and often moves on the last day, so the actual listing can be higher or lower. Track the <a class="link" href="{{ route('ipos.gmp') }}">live IPO GMP</a> in the run-up to listing, and work out your profit with the <a class="link" href="{{ route('calculators.show', 'ipo-profit') }}">IPO listing gain calculator</a>.</p>
            </div>
        </div>

        <x-faq :faqs="$faqs" title="IPO Listing Day: FAQs" />

        <nav class="card card-pad" aria-label="More IPO lists">
            <h2 class="card-title" style="margin-bottom:12px">Explore more IPO lists</h2>
            <div class="hub-links">
                <a class="chip" href="{{ route('ipos.allotment') }}">IPO Allotment Status</a>
                <a class="chip" href="{{ route('ipos.listed') }}">Recently Listed IPOs</a>
                <a class="chip" href="{{ route('ipos.current') }}">Current IPOs</a>
                <a class="chip" href="{{ route('ipos.upcoming') }}">Upcoming IPOs</a>
                <a class="chip" href="{{ route('ipos.gmp') }}">IPO GMP Today</a>
                <a class="chip" href="{{ route('ipos.calendar') }}">IPO Calendar</a>
            </div>
        </nav>

        <div class="note info">
            <x-icon name="info" />
            <span>GMP and the expected listing price are unofficial and indicative only. Listing dates follow SEBI's T+3 timeline and can change. Nothing here is investment advice.</span>
        </div>
    </div>
</div>
@endsection
