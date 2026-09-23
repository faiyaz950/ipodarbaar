@extends('layouts.app')

@php
    use App\Models\Ipo;
    $open = $boards['open'];
    $closingSoon = $open->take(5);
    $tabs = [
        'open' => ['label' => 'Open Now', 'icon' => 'zap', 'empty' => 'No IPO is open for subscription today. Check upcoming IPOs.'],
        'upcoming' => ['label' => 'Upcoming', 'icon' => 'calendar', 'empty' => 'No upcoming IPOs announced yet.'],
        'closed' => ['label' => 'Listing Soon', 'icon' => 'hourglass', 'empty' => 'No IPOs are awaiting listing right now.'],
        'listed' => ['label' => 'Recently Listed', 'icon' => 'check-circle', 'empty' => 'No recent listings.'],
    ];
    $defaultTab = $open->isNotEmpty() ? 'open' : 'upcoming';
@endphp

@section('content')

{{-- ============ HERO ============ --}}
<section class="hero">
    <div class="container">
        <div>
            <span class="hero-kicker">
                <b>LIVE</b>
                {{ $stats['open'] }} {{ $stats['open'] === 1 ? 'IPO' : 'IPOs' }} open today · {{ $stats['upcoming'] }} upcoming
            </span>
            <h1>Every IPO. Every GMP.<br><em>One Darbaar.</em></h1>
            <p class="hero-lead">Track mainboard & SME IPOs in India with live grey market premium, subscription dates, listing calendar, market news shorts and powerful calculators, all in one place.</p>

            <form class="hero-search" action="{{ route('ipos.index') }}" method="get" role="search">
                <label class="field">
                    <x-icon name="search" :size="20" />
                    <input type="search" name="q" placeholder="Search an IPO, e.g. Moneyview, Orient Cables…" aria-label="Search IPOs">
                </label>
                <button class="btn btn-gold" type="submit">Search IPOs <x-icon name="arrow-right" :size="16" /></button>
            </form>

            <div class="hero-links">
                <a href="{{ route('ipos.gmp') }}"><x-icon name="trending-up" :size="15" /> IPO GMP Today</a>
                <a href="{{ route('ipos.type', 'mainboard') }}"><x-icon name="building" :size="15" /> Mainboard</a>
                <a href="{{ route('ipos.type', 'sme') }}"><x-icon name="briefcase" :size="15" /> SME</a>
                <a href="{{ route('ipos.calendar') }}"><x-icon name="calendar" :size="15" /> Calendar</a>
                <a href="{{ route('news.shorts') }}"><x-icon name="smartphone" :size="15" /> News Shorts</a>
            </div>
        </div>

        <div class="hero-panel">
            <div class="hero-panel-head">
                <h3><x-icon name="flame" :size="17" style="color:#F2A93B" /> Closing Soon</h3>
                <a href="{{ route('ipos.index', ['status' => 'open']) }}">View all open →</a>
            </div>
            @forelse ($closingSoon as $ipo)
                <a class="hp-row" href="{{ $ipo->url() }}">
                    <x-logo-tile :ipo="$ipo" />
                    <div class="info">
                        <div class="name">{{ $ipo->name }}</div>
                        <div class="meta">
                            <span class="badge b-{{ $ipo->type }}" style="height:19px;font-size:10.5px">{{ $ipo->typeLabel() }}</span>
                            <span>{{ $ipo->priceBand() }}</span>
                            <span>·</span>
                            <span>{{ $ipo->countdown() }}</span>
                        </div>
                    </div>
                    <div class="right">
                        @if ($ipo->hasGmp())
                            <div class="gmpv {{ $ipo->gmp > 0 ? 'up' : 'flat' }}">{{ $ipo->gmp > 0 ? '+' : '' }}₹{{ Ipo::num($ipo->gmp) }}</div>
                            <div class="gmpl">{{ number_format($ipo->gmpPercent() ?? 0, 1) }}% GMP</div>
                        @else
                            <div class="gmpv flat">—</div>
                            <div class="gmpl">GMP</div>
                        @endif
                    </div>
                </a>
            @empty
                <div class="hp-empty">No IPO is open right now.<br><a href="{{ route('ipos.index', ['status' => 'upcoming']) }}" style="color:var(--gold-2)">See upcoming IPOs →</a></div>
            @endforelse
        </div>
    </div>
</section>

{{-- ============ STATS ============ --}}
<section class="stats">
    <div class="container">
        <div class="stats-grid">
            <a class="stat" href="{{ route('ipos.index', ['status' => 'open']) }}">
                <span class="ico ico-green"><x-icon name="zap" :size="22" /></span>
                <span><span class="kpi">{{ $stats['open'] }}</span><span class="lbl" style="display:block">Open for subscription</span></span>
            </a>
            <a class="stat" href="{{ route('ipos.index', ['status' => 'upcoming']) }}">
                <span class="ico ico-blue"><x-icon name="calendar" :size="22" /></span>
                <span><span class="kpi">{{ $stats['upcoming'] }}</span><span class="lbl" style="display:block">Upcoming IPOs</span></span>
            </a>
            <a class="stat" href="{{ route('ipos.index', ['status' => 'closed']) }}">
                <span class="ico ico-amber"><x-icon name="hourglass" :size="22" /></span>
                <span><span class="kpi">{{ $stats['closed'] }}</span><span class="lbl" style="display:block">Allotment / listing soon</span></span>
            </a>
            <a class="stat" href="{{ route('ipos.index') }}">
                <span class="ico ico-gold"><x-icon name="layers" :size="22" /></span>
                <span><span class="kpi">{{ number_format($stats['total']) }}</span><span class="lbl" style="display:block">IPOs tracked</span></span>
            </a>
        </div>
    </div>
</section>

{{-- ============ IPO BOARD + SIDEBAR ============ --}}
<section class="section">
    <div class="container layout">
        <div class="card" data-tabs>
            <div class="card-head">
                <div>
                    <div class="card-title"><span class="ico"><x-icon name="crown" /></span> IPO Dashboard</div>
                    <div class="card-sub">Live status of mainboard and SME issues</div>
                </div>
                <div class="seg" data-type-filter="board">
                    <button type="button" class="active" data-value="all">All</button>
                    <button type="button" data-value="mainboard">Mainboard</button>
                    <button type="button" data-value="sme">SME</button>
                </div>
            </div>
            <div style="padding: 14px 22px 0">
                <div class="tabs" role="tablist">
                    @foreach ($tabs as $key => $tab)
                        <button type="button" role="tab" class="tab {{ $key === $defaultTab ? 'active' : '' }}" data-tab="{{ $key }}"
                                data-more="{{ route('ipos.index', ['status' => $key]) }}" aria-selected="{{ $key === $defaultTab ? 'true' : 'false' }}">
                            <x-icon :name="$tab['icon']" :size="15" /> {{ $tab['label'] }}
                            @if ($key !== 'listed')<span class="count">{{ $stats[$key] }}</span>@endif
                        </button>
                    @endforeach
                </div>
            </div>
            <div id="board" style="margin-top: 14px; border-top: 1px solid var(--border)">
                @foreach ($tabs as $key => $tab)
                    <div class="tab-pane {{ $key === $defaultTab ? 'active' : '' }}" data-pane="{{ $key }}">
                        <x-ipo-table :ipos="$boards[$key]" :status="$key" :empty="$tab['empty']" />
                    </div>
                @endforeach
            </div>
            <div class="table-foot">
                <span class="updated"><x-icon name="refresh" :size="14" /> GMP is indicative and updated through the day</span>
                <a class="link-gold" data-tab-more href="{{ route('ipos.index', ['status' => $defaultTab]) }}">View full list <x-icon name="arrow-right" :size="15" /></a>
            </div>
        </div>

        <aside class="sidebar">
            @php $lead = $news[0] ?? null; @endphp
            <div class="promo">
                <span class="badge b-gold" style="background:rgba(230,190,98,.18);color:var(--gold-2)"><x-icon name="smartphone" :size="13" /> IPO Darbaar Shorts</span>
                <h3>Market news in 60 seconds</h3>
                <p>Swipe through bite-sized market stories in English or Hinglish.</p>
                @if ($lead)
                    <div class="phone-peek">
                        @if ($lead['image'])<img src="{{ $lead['image'] }}" alt="" loading="lazy">@endif
                        <span class="t">{{ $lead['headline'] }}</span>
                    </div>
                @endif
                <a href="{{ route('news.shorts') }}" class="btn btn-gold btn-block"><x-icon name="play" :size="15" /> Start swiping</a>
            </div>

            <div class="card widget">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="newspaper" :size="16" /></span> IPO News</div>
                    <a href="{{ route('news.index', ['category' => 'ipo']) }}" class="link-gold">All <x-icon name="chevron-right" :size="14" /></a>
                </div>
                @forelse (array_slice($ipoNews, 0, 4) as $n)
                    <a class="list-link" href="{{ $n['url'] }}">
                        @if ($n['image'])<img class="thumb" src="{{ $n['image'] }}" alt="" loading="lazy">@endif
                        <span>
                            <span class="t">{{ $n['headline'] }}</span>
                            <span class="m">{{ $n['date_label'] }}</span>
                        </span>
                    </a>
                @empty
                    <div class="empty" style="padding:26px"><p>News will appear here shortly.</p></div>
                @endforelse
            </div>

            <div class="card widget">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="calculator" :size="16" /></span> Quick Tools</div>
                </div>
                @foreach (array_slice($calculators, 0, 4) as $calc)
                    <a class="list-link" href="{{ route('calculators.show', $calc['slug']) }}">
                        <span class="ico"><x-icon :name="$calc['icon']" :size="17" /></span>
                        <span><span class="t" style="-webkit-line-clamp:1">{{ $calc['name'] }}</span></span>
                        <x-icon name="chevron-right" :size="16" class="chev" />
                    </a>
                @endforeach
            </div>
        </aside>
    </div>
</section>

{{-- ============ THIS WEEK ============ --}}
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">This week</span>
                <h2 class="section-title">IPO Calendar: {{ $week[0]['date']->format('j M') }} – {{ $week[6]['date']->format('j M') }}</h2>
            </div>
            <div class="legend">
                <span><i style="background:var(--green)"></i>Opens</span>
                <span><i style="background:var(--amber)"></i>Closes</span>
                <span><i style="background:var(--blue)"></i>Lists</span>
                <a class="link-gold" href="{{ route('ipos.calendar') }}">Full calendar <x-icon name="arrow-right" :size="15" /></a>
            </div>
        </div>
        <div class="week">
            @foreach ($week as $day)
                <div class="day {{ $day['today'] ? 'today' : '' }}">
                    <div class="day-head">
                        <span class="dname">{{ $day['today'] ? 'Today' : $day['date']->format('D') }}</span>
                        <span class="dnum">{{ $day['date']->format('j') }}</span>
                    </div>
                    @forelse (array_slice($day['events'], 0, 5) as $ev)
                        <a class="ev ev-{{ $ev['kind'] }}" href="{{ $ev['ipo']->url() }}" title="{{ $ev['ipo']->name }} {{ strtolower($ev['label']) }}">
                            <b>{{ $ev['label'] }}</b><span>{{ $ev['ipo']->name }}</span>
                        </a>
                    @empty
                        <div class="day-empty">No events</div>
                    @endforelse
                    @if (count($day['events']) > 5)
                        <a class="link-gold" style="font-size:12px" href="{{ route('ipos.calendar') }}">+{{ count($day['events']) - 5 }} more</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ NEWS ============ --}}
@if (count($news))
<section class="section" style="padding-top:8px">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Market pulse</span>
                <h2 class="section-title">Latest Market News</h2>
            </div>
            <div class="chips">
                @foreach (array_slice($categories, 0, 5) as $c)
                    <a class="chip" href="{{ route('news.index', ['category' => $c['slug']]) }}"><span class="cdot" style="background:{{ $c['color'] }}"></span>{{ $c['name'] }}</a>
                @endforeach
                <a class="chip active" href="{{ route('news.index') }}">All news <x-icon name="arrow-right" :size="14" /></a>
            </div>
        </div>
        <div class="news-grid">
            <x-news-card :item="$news[0]" :feature="true" />
            @foreach (array_slice($news, 1, 7) as $item)
                <x-news-card :item="$item" />
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============ CALCULATORS ============ --}}
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Smart tools</span>
                <h2 class="section-title">Calculators for every investor</h2>
                <p class="section-desc">From IPO listing gains and allotment odds to SIP, EMI and capital gains tax. Quick, accurate and free.</p>
            </div>
            <a href="{{ route('calculators.index') }}" class="btn btn-outline">All calculators <x-icon name="arrow-right" :size="16" /></a>
        </div>
        <div class="grid-4">
            @foreach ($calculators as $calc)
                <a class="calc-card" href="{{ route('calculators.show', $calc['slug']) }}">
                    <span class="ico"><x-icon :name="$calc['icon']" :size="22" /></span>
                    <h3>{{ $calc['name'] }}</h3>
                    <p>{{ $calc['short'] }}</p>
                    <span class="go">Calculate <x-icon name="arrow-right" :size="14" /></span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ WHY ============ --}}
<section class="section" style="padding-top:8px">
    <div class="container">
        <div class="features">
            <div class="feature">
                <span class="ico ico-green"><x-icon name="activity" :size="22" /></span>
                <h3>Live GMP tracking</h3>
                <p>Grey market premium and estimated listing price for every active IPO, refreshed through the day.</p>
            </div>
            <div class="feature">
                <span class="ico ico-blue"><x-icon name="calendar" :size="22" /></span>
                <h3>Complete IPO calendar</h3>
                <p>Opening, closing, allotment and listing dates for mainboard and SME issues at a glance.</p>
            </div>
            <div class="feature">
                <span class="ico ico-violet"><x-icon name="languages" :size="22" /></span>
                <h3>News in your language</h3>
                <p>Market stories as quick shorts, in English and Hinglish, so you never miss what moves the market.</p>
            </div>
            <div class="feature">
                <span class="ico ico-gold"><x-icon name="calculator" :size="22" /></span>
                <h3>{{ count(\App\Support\Calculators::all()) }} free calculators</h3>
                <p>Plan applications, estimate gains, compare returns and work out tax in seconds.</p>
            </div>
        </div>
    </div>
</section>

@endsection
