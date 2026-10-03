@extends('layouts.app')

@php
    use App\Models\Ipo;

    $withGmp = $active->filter(fn (Ipo $ipo): bool => $ipo->hasGmp());
    $highest = $withGmp->filter(fn (Ipo $ipo): bool => $ipo->gmp > 0 && $ipo->gmpPercent() !== null)
        ->sortByDesc(fn (Ipo $ipo): float => $ipo->gmpPercent())->values();
    $top = $highest->first();
    // The feed has no subscription figures; the column appears once an editor adds them.
    $showSubscription = $active->contains(fn (Ipo $ipo): bool => $ipo->subscription_total !== null);
    $today = now()->format('j M Y');

    // The same page serves all IPOs (/ipo-gmp) and one board (/mainboard-ipo-gmp, /sme-ipo-gmp).
    $board = match ($type) {
        'sme' => ['label' => 'SME IPO GMP', 'noun' => 'SME IPOs', 'route' => route('ipos.gmp.sme'),
            'lead' => 'Live grey market premium (GMP) of every open, upcoming and closed SME IPO on NSE Emerge and BSE SME, with the expected listing price and listing gain.'],
        'mainboard' => ['label' => 'Mainboard IPO GMP', 'noun' => 'mainboard IPOs', 'route' => route('ipos.gmp.mainboard'),
            'lead' => 'Live grey market premium (GMP) of every open, upcoming and closed mainboard IPO on NSE and BSE, with the expected listing price and listing gain.'],
        default => ['label' => 'IPO GMP', 'noun' => 'mainboard & SME IPOs', 'route' => route('ipos.gmp'),
            'lead' => 'Live grey market premium (GMP) of every open, upcoming and closed IPO, with the expected listing price and listing gain for mainboard and SME issues.'],
    };
    $gmpFaqs = [
        ['What is IPO GMP?', 'IPO GMP (grey market premium) is the premium over the issue price at which IPO shares are traded unofficially before listing. A GMP of ₹40 on an issue price of ₹200 means grey-market dealers expect the shares to list around ₹240.'],
        ['How is the expected listing price calculated from GMP?', 'Expected listing price = upper price band + GMP. Expected listing gain % = GMP ÷ upper price band × 100. Both are indicative only.'],
        ['Is IPO GMP reliable?', 'GMP reflects grey-market sentiment and often moves with overall market mood and subscription numbers. It is unofficial and unregulated, can change sharply until listing day, and has frequently been wrong. Use it as one signal among many, not as a guarantee.'],
        ['What is Kostak and Subject to Sauda?', 'Kostak is a fixed amount a grey-market dealer pays for an IPO application regardless of allotment. Subject to Sauda is paid only if the application gets an allotment. Both are unofficial arrangements with no legal protection.'],
        ['How often is the GMP on this page updated?', 'IPO Darbaar refreshes the grey market premium of every open, upcoming and closed IPO through the day. Each IPO page also shows its day-by-day GMP trend.'],
    ];
    if ($type === 'sme') {
        array_splice($gmpFaqs, 1, 0, [
            ['Why do SME IPO GMPs swing so much?', 'SME issues are small, so a few large orders can move demand sharply. Grey-market quotes for SME IPOs are thinner and change faster than for mainboard IPOs, and SME stocks trade in lots after listing, which limits liquidity.'],
            ['How much money do I need for an SME IPO?', 'Individual investors must apply for at least two lots in an SME IPO, which usually works out to more than ₹2 lakh. Each SME IPO page shows the exact lot size and amount.'],
        ]);
    }
@endphp

@section('title', ($type ? $board['label'].' Today ('.$today.'): Live Grey Market Premium' : 'Live IPO GMP Today ('.$today.'): Grey Market Premium'))
@section('description', 'Live '.$board['label'].' today ('.$today.') for '.$active->count().' '.$board['noun'].': grey market premium, expected listing price and gain %.'.($top ? ' Highest now: '.$top->name.' '.number_format($top->gmpPercent(), 1).'%.' : ''))

@push('head')
<x-jsonld :breadcrumbs="$type ? [['Home', route('home')], ['IPO GMP Today', route('ipos.gmp')], [$board['label'], $board['route']]] : [['Home', route('home')], ['IPO GMP Today', route('ipos.gmp')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $type ? $board['label'].' Today' : 'Live IPO GMP Today',
    'url' => $board['route'],
    'dateModified' => (\App\Services\IpoSyncService::lastSyncedAt() ?? now())->toIso8601String(),
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $withGmp->count(),
        'itemListElement' => $withGmp->values()->map(fn (Ipo $ipo, int $i): array => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => $ipo->url().'#gmp',
            'name' => $ipo->name.' IPO GMP',
        ])->all(),
    ],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            @if ($type)
                <a href="{{ route('ipos.gmp') }}">IPO GMP</a> <x-icon name="chevron-right" :size="13" /> <span>{{ $board['label'] }}</span>
            @else
                <span>IPO GMP</span>
            @endif
        </nav>
        <h1>{{ $type ? $board['label'].' Today' : 'Live IPO GMP Today' }}</h1>
        <p class="lead">{{ $board['lead'] }}</p>
        <div class="hub-links" style="margin-top:12px">
            <a class="chip {{ $type === null ? 'active' : '' }}" href="{{ route('ipos.gmp') }}">All IPO GMP</a>
            <a class="chip {{ $type === 'mainboard' ? 'active' : '' }}" href="{{ route('ipos.gmp.mainboard') }}">Mainboard IPO GMP</a>
            <a class="chip {{ $type === 'sme' ? 'active' : '' }}" href="{{ route('ipos.gmp.sme') }}">SME IPO GMP</a>
        </div>
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Updated {{ (\App\Services\IpoSyncService::lastSyncedAt() ?? now())->timezone(config('app.timezone'))->format('j M Y, g:i A') }} IST · {{ $withGmp->count() }} IPOs with GMP</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="grid-3">
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-green" style="margin:0"><x-icon name="trending-up" :size="20" /></span>
                <div><h3>What is GMP?</h3><p>The premium at which IPO shares trade unofficially before listing, a gauge of market sentiment.</p></div>
            </div>
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-blue" style="margin:0"><x-icon name="target" :size="20" /></span>
                <div><h3>Estimated listing</h3><p>Issue price + GMP gives an indicative listing price. GMP ÷ price gives the expected gain %.</p></div>
            </div>
            <div class="feature" style="display:flex; gap:14px; align-items:flex-start">
                <span class="ico ico-amber" style="margin:0"><x-icon name="alert" :size="20" /></span>
                <div><h3>Use with caution</h3><p>GMP is unregulated and volatile. Always read the RHP and consider fundamentals before applying.</p></div>
            </div>
        </div>

        @if ($highest->isNotEmpty())
            <div class="card card-pad">
                <h2 class="card-title" id="highest-gmp" style="margin-bottom:10px"><span class="ico"><x-icon name="trending-up" :size="16" /></span> Highest {{ $board['label'] }} Today</h2>
                <ol class="rank-list">
                    @foreach ($highest->take(5) as $ipo)
                        <li>
                            <a href="{{ $ipo->url() }}#gmp">{{ $ipo->name }} IPO</a>:
                            GMP <b class="up">+₹{{ Ipo::num($ipo->gmp) }}</b> ({{ number_format($ipo->gmpPercent(), 1) }}%), expected listing ₹{{ Ipo::num($ipo->estListingPrice()) }}
                            <span class="muted">· {{ $ipo->typeLabel() }} · {{ match ($ipo->status()) { 'open' => 'closes '.($ipo->close_date ?? $ipo->open_date)->format('j M'), 'upcoming' => $ipo->open_date ? 'opens '.$ipo->open_date->format('j M') : 'dates awaited', default => 'lists '.($ipo->listing_date?->format('j M') ?? 'TBA') } }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="live-gmp"><span class="ico"><x-icon name="activity" :size="16" /></span> {{ $type ? 'Live '.$board['label'].' Today' : 'Live IPO GMP Today: Mainboard & SME' }}</h2>
                    <div class="card-sub">{{ $active->count() }} active {{ $board['noun'] }} · open, upcoming & awaiting listing</div>
                </div>
                @unless ($type)
                    <div class="seg" data-type-filter="gmp-active">
                        <button type="button" class="active" data-value="all">All</button>
                        <button type="button" data-value="mainboard">Mainboard</button>
                        <button type="button" data-value="sme">SME</button>
                    </div>
                @endunless
            </div>
            <div class="table-wrap" id="gmp-active">
                <table class="table">
                    <thead>
                        <tr>
                            <th>IPO</th>
                            <th class="r">Price</th>
                            <th class="r">GMP</th>
                            <th class="r">Est. Listing</th>
                            <th class="r">Est. Gain</th>
                            @if ($showSubscription)<th class="r">Subscription</th>@endif
                            <th>Open – Close</th>
                            <th>Allotment</th>
                            <th>Listing</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($active as $ipo)
                            @php $cls = ! $ipo->hasGmp() ? 'flat' : ($ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : 'flat')); @endphp
                            <tr data-type="{{ $ipo->type }}">
                                <td class="company">
                                    <div class="co">
                                        <x-logo-tile :ipo="$ipo" />
                                        <div>
                                            <a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a>
                                            <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span>
                                                @if ($ipo->issue_size)<span>₹{{ Ipo::num($ipo->issue_size) }} Cr</span>@endif
                                                @if ($ipo->source_updated_at)<span>Updated {{ $ipo->source_updated_at->diffForHumans(null, true) }} ago</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="r">
                                    {{ $ipo->price ? '₹'.Ipo::num($ipo->price) : '—' }}
                                    @if ($ipo->lot_size)<small class="muted cell-sub">Lot {{ number_format($ipo->lot_size) }}</small>@endif
                                </td>
                                <td class="r"><b class="{{ $cls }}">{{ $ipo->hasGmp() ? ($ipo->gmp > 0 ? '+' : '').'₹'.Ipo::num($ipo->gmp) : '—' }}</b></td>
                                <td class="r">{{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}</td>
                                <td class="r">
                                    @if ($ipo->gmpPercent() !== null)
                                        <span class="gmp-pill {{ $cls }} {{ $cls }}">{{ $ipo->gmp > 0 ? '▲' : ($ipo->gmp < 0 ? '▼' : '') }} {{ number_format(abs($ipo->gmpPercent()), 2) }}%</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                @if ($showSubscription)<td class="r">{{ $ipo->subscription_total !== null ? number_format($ipo->subscription_total, 2).'x' : '—' }}</td>@endif
                                <td class="dates">{{ $ipo->open_date ? $ipo->open_date->format('j M').' – '.($ipo->close_date ?? $ipo->open_date)->format('j M') : 'Awaited' }}</td>
                                <td>{{ ($allotmentDates[$ipo->id] ?? null)?->format('j M') ?? 'TBA' }}</td>
                                <td>{{ $ipo->listing_date?->format('j M') ?? 'TBA' }}</td>
                                <td><x-status-badge :ipo="$ipo" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showSubscription ? 10 : 9 }}"><div class="table-empty">No active IPOs right now.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($recent->isNotEmpty())
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="recently-listed"><span class="ico"><x-icon name="check-circle" :size="16" /></span> Recently Listed IPOs: Last GMP vs Listing</h2>
                    <div class="card-sub">IPOs listed in the last 30 days with their final grey market premium</div>
                </div>
            </div>
            <x-ipo-table :ipos="$recent" status="listed" />
        </div>
        @endif

        <div class="card card-pad seo-copy">
            <div>
                <h2>How to read IPO GMP</h2>
                <p>The grey market premium is what unofficial dealers are willing to pay over the IPO's upper price band before the shares list. Add the GMP to the issue price to get the expected listing price; divide it by the issue price for the expected listing gain. A rising GMP usually tracks strong subscription, while a falling or negative GMP signals weak demand.</p>
            </div>
            <div>
                <h2>GMP is a signal, not a promise</h2>
                <p>The grey market is unregulated and GMP can swing sharply with overall market mood, especially in the last two days before listing. Compare it with subscription numbers, the company's financials and valuation before deciding. Read our guide on <a class="link" href="{{ route('guides.show', 'what-is-ipo-gmp') }}">what IPO GMP is and how reliable it is</a>, or estimate your profit with the <a class="link" href="{{ route('calculators.show', 'ipo-gmp') }}">IPO GMP calculator</a>. To see how often GMP has matched the actual listing, check our <a class="link" href="{{ route('ipos.gmp-accuracy') }}">GMP accuracy tracker</a>.</p>
            </div>
        </div>

        <x-faq :faqs="$gmpFaqs" :title="$board['label'].': FAQs'" />
    </div>
</div>
@endsection
