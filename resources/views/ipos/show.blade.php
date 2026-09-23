@extends('layouts.app')

@php
    use App\Models\Ipo;
    $gmpCls = ! $ipo->hasGmp() ? 'flat' : ($ipo->gmp > 0 ? 'up' : ($ipo->gmp < 0 ? 'down' : 'flat'));
    $lotTable = $ipo->lotTable();
    $timeline = $ipo->timeline();
@endphp

@section('title', $ipo->name.' IPO: GMP, Price Band, Dates & Details')
@section('description', $ipo->metaDescription())
@section('og_image', $ipo->bannerUrl() ?? '')

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $ipo->name.' IPO',
    'description' => $ipo->metaDescription(),
    'startDate' => $ipo->open_date?->toDateString(),
    'endDate' => ($ipo->close_date ?? $ipo->open_date)?->toDateString(),
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
    'location' => ['@type' => 'VirtualLocation', 'url' => $ipo->url()],
    'image' => $ipo->bannerUrl(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<section class="page-head ipo-hero">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('ipos.type', $ipo->type) }}">{{ $ipo->typeLabel() }} IPOs</a> <x-icon name="chevron-right" :size="13" />
            <span>{{ $ipo->name }}</span>
        </nav>

        <div class="ipo-hero-grid">
            <div class="ipo-id">
                <x-logo-tile :ipo="$ipo" size="xl" />
                <div>
                    <h1>{{ $ipo->name }} IPO</h1>
                    <div class="badges">
                        <x-status-badge :ipo="$ipo" />
                        <span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }} IPO</span>
                        <span class="badge b-plain-dark">{{ $ipo->exchangeLabel() }}</span>
                    </div>
                    @if ($ipo->countdown())
                        <div class="ipo-countdown"><x-icon name="clock" :size="16" /> <b>{{ $ipo->countdown() }}</b>
                            @if ($ipo->source_updated_at)<span style="color:#8F9BBD">· updated {{ $ipo->source_updated_at->diffForHumans() }}</span>@endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="price-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:6px">
                    <div>
                        <div style="font-size:13px;color:#9AA6C7">Price band</div>
                        <div class="big">{{ $ipo->priceBand() }}</div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:13px;color:#9AA6C7">GMP</div>
                        <div class="big gmpbig {{ $gmpCls }}" style="font-size:24px">
                            @if ($ipo->hasGmp()){{ $ipo->gmp > 0 ? '+' : '' }}₹{{ Ipo::num($ipo->gmp) }}@else — @endif
                        </div>
                    </div>
                </div>
                <div class="row"><span>Est. listing price</span><b>{{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}</b></div>
                <div class="row"><span>Est. listing gain</span><b style="color: {{ $gmpCls === 'up' ? '#52D6A4' : ($gmpCls === 'down' ? '#FF9191' : '#fff') }}">{{ $ipo->gmpPercent() !== null ? number_format($ipo->gmpPercent(), 2).'%' : '—' }}</b></div>
                <div class="row"><span>Issue size</span><b>{{ $ipo->issue_size ? '₹'.Ipo::num($ipo->issue_size).' Cr' : '—' }}</b></div>
                <div class="actions">
                    <a href="{{ $ipo->calculatorUrl() }}" class="btn btn-gold btn-sm"><x-icon name="calculator" :size="15" /> GMP Calculator</a>
                    <button type="button" class="btn btn-ghost-light btn-sm" data-share="native" data-title="{{ $ipo->name }} IPO: GMP & details" data-url="{{ $ipo->url() }}"><x-icon name="share" :size="15" /> Share</button>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container layout">
        <div class="stack">

            {{-- Key facts --}}
            <div class="card">
                <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="layers" :size="16" /></span> IPO Details</div></div>
                <div class="facts-grid">
                    <div class="fact"><span><x-icon name="calendar" :size="14" /> Open Date</span><b>{{ $ipo->open_date?->format('D, j M Y') ?? 'Awaited' }}</b></div>
                    <div class="fact"><span><x-icon name="calendar" :size="14" /> Close Date</span><b>{{ $ipo->close_date?->format('D, j M Y') ?? 'Awaited' }}</b></div>
                    <div class="fact"><span><x-icon name="rocket" :size="14" /> Listing Date</span><b>{{ $ipo->listing_date?->format('D, j M Y') ?? 'Awaited' }}</b></div>
                    <div class="fact"><span><x-icon name="landmark" :size="14" /> Listing At</span><b>{{ $ipo->exchangeLabel() }}</b></div>
                    <div class="fact"><span><x-icon name="banknote" :size="14" /> Price Band</span><b>{{ $ipo->priceBand() }} <small>per share</small></b></div>
                    <div class="fact"><span><x-icon name="grid" :size="14" /> Lot Size</span><b>{{ $ipo->lot_size ? number_format($ipo->lot_size).' shares' : 'Awaited' }}</b></div>
                    <div class="fact"><span><x-icon name="wallet" :size="14" /> Min. Investment</span><b>{{ $lotTable ? '₹'.Ipo::num($lotTable[0]['amount']) : 'Awaited' }}</b></div>
                    <div class="fact"><span><x-icon name="pie" :size="14" /> Issue Size</span><b>{{ $ipo->issue_size ? '₹'.Ipo::num($ipo->issue_size).' Cr' : 'Awaited' }}</b></div>
                </div>
            </div>

            {{-- Timeline --}}
            @if ($ipo->open_date)
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="clock" :size="16" /></span> IPO Timeline</div>
                    <span class="card-sub">Tentative dates follow SEBI's T+3 listing timeline</span>
                </div>
                <div class="timeline">
                    @foreach ($timeline as $step)
                        <div class="tl-step {{ $step['done'] ? 'done' : '' }} {{ $step['current'] ? 'current' : '' }}">
                            <div class="tl-dot">
                                @if ($step['done'])<x-icon name="check" :size="15" :stroke="3" />@else<span style="width:8px;height:8px;border-radius:50%;background:currentColor;display:block"></span>@endif
                            </div>
                            <div class="tl-label">{{ $step['label'] }}</div>
                            <div>
                                <div class="tl-date">{{ $step['date']?->format('D, j M') ?? 'TBA' }}</div>
                                @if ($step['tentative'] && $step['date'])<div class="tl-tent">Tentative</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- GMP --}}
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="trending-up" :size="16" /></span> {{ $ipo->name }} IPO GMP</div>
                    <a class="link-gold" href="{{ route('ipos.gmp') }}">All IPO GMP <x-icon name="arrow-right" :size="14" /></a>
                </div>
                <div class="gmp-panel">
                    <div class="cell">
                        <span>GMP today</span>
                        <b class="{{ $gmpCls }}">@if ($ipo->hasGmp()){{ $ipo->gmp > 0 ? '+' : '' }}₹{{ Ipo::num($ipo->gmp) }}@else — @endif</b>
                    </div>
                    <div class="cell">
                        <span>Est. listing price</span>
                        <b>{{ $ipo->estListingPrice() ? '₹'.Ipo::num($ipo->estListingPrice()) : '—' }}</b>
                    </div>
                    <div class="cell">
                        <span>Est. gain %</span>
                        <b class="{{ $gmpCls }}">{{ $ipo->gmpPercent() !== null ? number_format($ipo->gmpPercent(), 2).'%' : '—' }}</b>
                        @if ($ipo->gmpPercent() !== null)
                            <div class="gmp-meter {{ $gmpCls === 'down' ? 'down' : '' }}"><i style="width: {{ min(100, abs($ipo->gmpPercent())) }}%"></i></div>
                        @endif
                    </div>
                    <div class="cell">
                        <span>Est. profit / lot</span>
                        <b class="{{ $gmpCls }}">{{ $ipo->gmpLotProfit() !== null ? '₹'.Ipo::num($ipo->gmpLotProfit()) : '—' }}</b>
                    </div>
                </div>
                <div style="padding: 0 22px 20px">
                    <div class="note">
                        <x-icon name="alert" />
                        <span>{{ $ipo->hasGmp() ? 'GMP is an unofficial grey-market indicator and can change quickly. It does not guarantee listing gains.' : 'GMP for this IPO is not available yet. It usually appears a few days before the issue opens.' }}
                            <a class="link" href="{{ $ipo->calculatorUrl() }}">Try the GMP calculator</a></span>
                    </div>
                </div>
            </div>

            {{-- Lot table --}}
            @if ($lotTable)
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="users" :size="16" /></span> Lot Size & Investment</div>
                    <span class="card-sub">At upper price band of ₹{{ Ipo::num($ipo->price) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Application</th><th class="r">Lots</th><th class="r">Shares</th><th class="r">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($lotTable as $row)
                                <tr>
                                    <td><b>{{ $row['category'] }}</b></td>
                                    <td class="r">{{ number_format($row['lots']) }}</td>
                                    <td class="r">{{ number_format($row['shares']) }}</td>
                                    <td class="r"><b>₹{{ Ipo::num($row['amount']) }}</b></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- About --}}
            <div class="card card-pad">
                <div class="card-title" style="margin-bottom:14px"><span class="ico"><x-icon name="building" :size="16" /></span> About {{ $ipo->name }} IPO</div>
                <div class="prose">
                    @if ($ipo->description)<p>{{ $ipo->description }}</p>@endif
                    @php
                        $sentences = [];
                        $first = e($ipo->name).' is '.($ipo->isMainboard() ? 'a mainboard' : 'an SME').' IPO';
                        if ($ipo->issue_size) $first .= ' worth <strong>₹'.Ipo::num($ipo->issue_size).' crore</strong>';
                        if ($ipo->price) $first .= ' with a price band of <strong>'.e($ipo->priceBand()).'</strong> per share';
                        $sentences[] = $first.'.';
                        if ($ipo->open_date) {
                            $sentences[] = 'The issue opens on <strong>'.$ipo->open_date->format('j F Y').'</strong>'
                                .($ipo->close_date ? ' and closes on <strong>'.$ipo->close_date->format('j F Y').'</strong>' : '').'.';
                        }
                        if ($ipo->listing_date) {
                            $sentences[] = 'Shares are expected to list on <strong>'.$ipo->listing_date->format('j F Y').'</strong> on '.e($ipo->exchangeLabel()).'.';
                        }
                    @endphp
                    <p>{!! implode(' ', $sentences) !!}</p>
                </div>
                @if ($ipo->bannerUrl())
                    <img src="{{ $ipo->bannerUrl() }}" alt="{{ $ipo->name }} IPO" loading="lazy" style="border-radius:14px; margin-top:8px; border:1px solid var(--border); width:100%; max-width:560px">
                @endif
            </div>

            <div class="card card-pad">
                <div class="share-row">
                    <span class="lbl">Share this IPO</span>
                    <button type="button" class="sq-btn" data-share="whatsapp" data-title="{{ $ipo->name }} IPO: GMP & details" data-url="{{ $ipo->url() }}" aria-label="Share on WhatsApp"><x-icon name="message" :size="17" /></button>
                    <button type="button" class="sq-btn" data-share="x" data-title="{{ $ipo->name }} IPO: GMP & details" data-url="{{ $ipo->url() }}" aria-label="Share on X"><x-icon name="share" :size="17" /></button>
                    <button type="button" class="sq-btn" data-share="copy" data-url="{{ $ipo->url() }}" aria-label="Copy link"><x-icon name="link" :size="17" /></button>
                </div>
            </div>
        </div>

        <aside class="sidebar">
            <div class="card widget">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="layers" :size="16" /></span> Other IPOs</div>
                    <a class="link-gold" href="{{ route('ipos.index') }}">All <x-icon name="chevron-right" :size="14" /></a>
                </div>
                @foreach ($related as $r)
                    <a class="list-link" href="{{ $r->url() }}">
                        <x-logo-tile :ipo="$r" />
                        <span style="min-width:0">
                            <span class="t" style="-webkit-line-clamp:1">{{ $r->name }}</span>
                            <span class="m"><span class="badge b-{{ $r->status() }}" style="height:18px;font-size:10.5px">{{ $r->statusLabel() }}</span> {{ $r->priceBand() }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            @if (count($ipoNews))
            <div class="card widget">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="newspaper" :size="16" /></span> IPO News</div>
                </div>
                @foreach (array_slice($ipoNews, 0, 4) as $n)
                    <a class="list-link" href="{{ $n['url'] }}">
                        <span><span class="t">{{ $n['headline'] }}</span><span class="m">{{ $n['date_label'] }}</span></span>
                    </a>
                @endforeach
            </div>
            @endif

            <div class="promo">
                <span class="badge" style="background:rgba(230,190,98,.18);color:var(--gold-2)"><x-icon name="ticket" :size="13" /> Allotment odds</span>
                <h3>Will you get an allotment?</h3>
                <p>Estimate your chances from the subscription figure and number of applications.</p>
                <a href="{{ route('calculators.show', 'ipo-allotment-chance') }}" class="btn btn-gold btn-block">Check my chances</a>
            </div>
        </aside>
    </div>
</div>
@endsection
