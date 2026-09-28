@extends('layouts.app')

@php
    use App\Models\Ipo;
    use App\Support\IpoHubs;

    $page = $ipos->currentPage();
    $pageSuffix = $page > 1 ? ' – Page '.$page : '';
    $title = IpoHubs::fill($config['title'], $fill).$pageSuffix;
    $h1 = IpoHubs::fill($config['h1'], $fill);
    $hubUrl = route('ipos.'.$hub);
    $related = collect(IpoHubs::all())->except($hub)->map(fn (array $h, string $key): array => ['url' => route('ipos.'.$key), 'label' => $h['label']]);

    // Upcoming and open IPOs are split by board, with a short daily summary and a spotlight list.
    $listed = $ipos->getCollection();
    $grouped = isset($config['groups']) && ! $filter && ! $ipos->hasPages() && $listed->isNotEmpty();
    $boards = $grouped ? $listed->groupBy('type') : collect();
    $summary = null;
    $spotlight = collect();
    $spotlightTitle = null;
    if ($grouped) {
        $mainboardCount = $boards->get('mainboard', collect())->count();
        $smeCount = $boards->get('sme', collect())->count();
        $largest = $listed->filter(fn (Ipo $ipo): bool => (bool) $ipo->issue_size)->sortByDesc('issue_size')->first();
        $summary = 'As of '.now()->format('j M Y').', '.$listed->count().' '.($listed->count() === 1 ? 'IPO is' : 'IPOs are')
            .($hub === 'upcoming' ? ' lined up' : ' open for subscription').': '.$mainboardCount.' mainboard and '.$smeCount.' SME.'
            .($largest ? ' The largest is '.$largest->name.' at ₹'.Ipo::num($largest->issue_size).' crore.' : '');

        if ($hub === 'upcoming') {
            $weekEnd = today()->addDays(6);
            $spotlight = $listed->filter(fn (Ipo $ipo): bool => $ipo->open_date !== null && $ipo->open_date->lte($weekEnd))->values();
            $spotlightTitle = 'Upcoming IPOs This Week ('.today()->format('j M').' – '.$weekEnd->format('j M').')';
        } else {
            $spotlight = $listed->filter(fn (Ipo $ipo): bool => (bool) ($ipo->close_date ?? $ipo->open_date)?->isToday())->values();
            $spotlightTitle = 'IPOs Closing Today ('.today()->format('j M').')';
        }
    }
@endphp

@section('title', $title)
@section('description', IpoHubs::fill($config['description'], $fill))
@section('robots', $filter ? 'noindex, follow' : 'index, follow')
@section('canonical', $filter ? $hubUrl : $hubUrl.($page > 1 ? '?page='.$page : ''))

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO', route('ipos.index')], [$config['label'], $hubUrl]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $h1,
    'url' => $hubUrl,
    'description' => IpoHubs::fill($config['description'], $fill),
    'dateModified' => now()->toIso8601String(),
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'IPO Darbaar', 'url' => route('home')],
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $ipos->total(),
        'itemListElement' => $ipos->getCollection()->values()->map(fn (Ipo $ipo, int $i): array => [
            '@type' => 'ListItem',
            'position' => $ipos->firstItem() + $i,
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
            <span>{{ $config['label'] }}</span>
        </nav>
        <h1>{{ $h1 }}</h1>
        <p class="lead">{{ $config['lead'] }}</p>
        @if ($summary && $page === 1)
            <p class="hub-summary">{{ $summary }}</p>
        @endif
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Updated {{ now()->format('j M Y, g:i A') }} IST · {{ number_format($ipos->total()) }} {{ $ipos->total() === 1 ? 'IPO' : 'IPOs' }}</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="filterbar">
                <div class="chips">
                    @if ($config['type'])
                        <a class="chip {{ ! $filter ? 'active' : '' }}" href="{{ $hubUrl }}">All</a>
                        @foreach (Ipo::STATUSES as $key => $label)
                            <a class="chip {{ $filter === $key ? 'active' : '' }}" href="{{ $hubUrl.'?status='.$key }}">{{ $label }}</a>
                        @endforeach
                    @else
                        <a class="chip {{ ! $filter ? 'active' : '' }}" href="{{ $hubUrl }}">All</a>
                        <a class="chip {{ $filter === 'mainboard' ? 'active' : '' }}" href="{{ $hubUrl.'?type=mainboard' }}">Mainboard</a>
                        <a class="chip {{ $filter === 'sme' ? 'active' : '' }}" href="{{ $hubUrl.'?type=sme' }}">SME</a>
                    @endif
                </div>
                <a class="link-gold" href="{{ route('ipos.gmp') }}"><x-icon name="trending-up" :size="14" /> Live GMP of all IPOs</a>
            </div>

            @if ($hub === 'allotment')
                <div class="table-wrap">
                    <table class="table ipo-table">
                        <thead>
                            <tr>
                                <th>IPO</th>
                                <th>Allotment date</th>
                                <th>Registrar</th>
                                <th>Listing</th>
                                <th class="r">Check status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ipos as $ipo)
                                @php
                                    $registrar = $ipo->registrarInfo();
                                    $allotment = $allotmentDates[$ipo->id] ?? null;
                                @endphp
                                <tr data-type="{{ $ipo->type }}">
                                    <td class="company">
                                        <div class="co">
                                            <x-logo-tile :ipo="$ipo" />
                                            <div style="min-width:0">
                                                <span class="co-title"><a href="{{ $ipo->url() }}#allotment" class="co-name">{{ $ipo->name }} IPO allotment status</a></span>
                                                <div class="co-meta"><span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span> <x-status-badge :ipo="$ipo" /></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Allotment date">
                                        {{ $allotment?->format('D, j M') ?? '—' }}
                                        @if ($allotment?->isToday())<small class="countdown hot">Today</small>@elseif ($allotment?->isFuture())<small class="muted">Tentative</small>@endif
                                    </td>
                                    <td data-label="Registrar">{{ $registrar['name'] ?? 'Awaited' }}</td>
                                    <td data-label="Listing">{{ $ipo->listing_date?->format('j M') ?? 'TBA' }}</td>
                                    <td class="r" data-label="Check status">
                                        @if ($registrar['url'] ?? null)
                                            <a class="btn btn-gold btn-sm" href="{{ $registrar['url'] }}" target="_blank" rel="noopener nofollow">Registrar <x-icon name="external" :size="13" /></a>
                                        @else
                                            <a class="btn btn-outline btn-sm" href="{{ $ipo->url() }}#allotment">How to check</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="table-empty">No IPO is awaiting allotment right now.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @elseif ($grouped)
                @foreach ($config['groups'] as $board => $heading)
                    @if ($boards->has($board))
                        <h2 class="group-title" id="{{ $board }}">{{ $heading }} <span class="muted">({{ $boards[$board]->count() }})</span></h2>
                        <x-ipo-table :ipos="$boards[$board]" :status="$status" />
                    @endif
                @endforeach
            @else
                <x-ipo-table :ipos="$ipos" :status="$status" :empty="'No '.strtolower($config['label']).' right now. Check the IPO calendar for what is coming next.'" />
            @endif

            @if ($ipos->hasPages())
                <x-ad-slot name="list" style="margin:16px 20px 0" />
                <div style="border-top: 1px solid var(--border)">{{ $ipos->onEachSide(1)->links() }}</div>
            @endif
        </div>

        @if ($spotlight->isNotEmpty())
            <div class="card card-pad">
                <h2 class="card-title" id="spotlight" style="margin-bottom:10px"><span class="ico"><x-icon name="calendar" :size="16" /></span> {{ $spotlightTitle }}</h2>
                <ol class="rank-list">
                    @foreach ($spotlight as $ipo)
                        <li>
                            <a href="{{ $ipo->url() }}">{{ $ipo->name }} IPO</a>
                            ({{ $ipo->typeLabel() }}):
                            @if ($hub === 'upcoming')
                                opens {{ $ipo->open_date->format('D, j M') }}, closes {{ ($ipo->close_date ?? $ipo->open_date)->format('D, j M') }}
                            @else
                                closes today at 5 PM{{ $ipo->subscription_total !== null ? ', subscribed '.number_format($ipo->subscription_total, 2).'x so far' : '' }}
                            @endif
                            · price band {{ $ipo->priceBand() }}{{ $ipo->hasGmp() ? ' · GMP '.($ipo->gmp >= 0 ? '₹' : '-₹').Ipo::num(abs($ipo->gmp)) : '' }}
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($page === 1)
            <div class="card card-pad seo-copy">
                @foreach ($config['about'] as [$heading, $text])
                    <div>
                        <h2>{{ $heading }}</h2>
                        <p>{{ $text }}</p>
                    </div>
                @endforeach
                @if ($hub === 'allotment')
                    <div>
                        <h2>Exchange allotment pages</h2>
                        <div class="hub-links">
                            @foreach (config('ipodarbar.allotment_links') as $link)
                                <a class="btn btn-outline btn-sm" href="{{ $link['url'] }}" target="_blank" rel="noopener nofollow">{{ $link['name'] }} allotment status <x-icon name="external" :size="13" /></a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-faq :faqs="$config['faqs']" :title="$config['label'].': FAQs'" />
        @endif

        <nav class="card card-pad" aria-label="More IPO lists">
            <h2 class="card-title" style="margin-bottom:12px">Explore more IPO lists</h2>
            <div class="hub-links">
                @foreach ($related as $link)
                    <a class="chip" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
                <a class="chip" href="{{ route('ipos.gmp') }}">IPO GMP Today</a>
                <a class="chip" href="{{ route('ipos.calendar') }}">IPO Calendar</a>
                <a class="chip" href="{{ route('ipos.year', now()->year) }}">IPO List {{ now()->year }}</a>
            </div>
        </nav>

        <div class="note info">
            <x-icon name="info" />
            <span>GMP (grey market premium) is unofficial and indicative only. Allotment and listing dates after the issue closes follow SEBI's T+3 timeline and may change. Nothing here is investment advice.</span>
        </div>
    </div>
</div>
@endsection
