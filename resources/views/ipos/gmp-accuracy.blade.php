@extends('layouts.app')

@php
    use App\Models\Ipo;

    $s = $accuracy['summary'];
    $main = $accuracy['boards']['Mainboard'];
    $sme = $accuracy['boards']['SME'];
    $pct = fn (?float $v): string => $v === null ? '—' : ($v > 0 ? '+' : '').number_format($v, 1).'%';
    $pts = fn (?float $v): string => $v === null ? '—' : ($v > 0 ? '+' : '').number_format($v, 1).' pts';
    $share = fn (?float $v): string => $v === null ? '—' : number_format($v, 0).'%';
    $trend = fn (?float $v): string => $v === null ? 'flat' : ($v > 0 ? 'up' : ($v < 0 ? 'down' : 'flat'));
    $bands = collect($accuracy['bands'])->where('count', '>', 0);
    $negative = $bands->firstWhere('label', 'GMP zero or negative');
    $high = $bands->firstWhere('label', 'GMP above 50%');
    $maxBand = max(1, $bands->flatMap(fn (array $b): array => [abs((float) $b['expected']), abs((float) $b['actual'])])->max());

    $faqs = $s['count'] ? array_values(array_filter([
        ['How accurate is IPO GMP?', 'Across '.$s['count'].' IPOs that listed since '.$accuracy['since'].', the listing gain landed a median '.number_format($s['median_abs_error'], 1).' percentage points away from what the last GMP pointed to. '.$share($s['within10']).' of listings were within 10 points of the GMP estimate, and the GMP got the direction (premium or discount) right '.$share($s['direction']).' of the time.'],
        $main['count'] && $sme['count'] ? ['Is GMP more accurate for mainboard or SME IPOs?', 'Mainboard. For mainboard IPOs the median miss was '.number_format($main['median_abs_error'], 1).' points with '.$share($main['within10']).' within 10 points; for SME IPOs it was '.number_format($sme['median_abs_error'], 1).' points with '.$share($sme['within10']).' within 10 points. SME grey markets are thinner, so their quotes move more and say less.'] : null,
        $high ? ['Does a high GMP guarantee listing gains?', 'No, but it has been a strong signal. IPOs whose GMP pointed to a gain above 50% listed '.$pct($high['actual']).' higher on average, and '.$share($high['premium_share']).' of them listed at a premium. Individual listings still varied widely, and SME listings are capped at 90% above the issue price on the first day.'] : null,
        $negative ? ['What happens when GMP is zero or negative?', 'In our data, IPOs with a zero or negative GMP before listing opened '.$pct($negative['actual']).' on average against the issue price, and '.$share($negative['premium_share']).' listed at a premium. A weak GMP has usually meant a weak listing.'] : null,
        ['Where do the listing prices come from?', 'The listing price is the first trade on listing day from NSE, or BSE for issues listed only there, taken from the exchanges\' official price files. The GMP is the last daily grey market premium recorded on IPO Darbaar before listing day.'],
    ])) : [];
@endphp

@section('title', 'IPO GMP Accuracy Tracker: Is GMP Right About Listing?')
@section('description', $s['count']
    ? 'We compared the last GMP before listing with the actual listing price of '.$s['count'].' IPOs. Median miss '.number_format($s['median_abs_error'], 1).' points; '.$share($s['within10']).' within 10 points.'
    : 'How accurately the IPO grey market premium (GMP) predicts the actual listing price, tracked IPO by IPO.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO GMP', route('ipos.gmp')], ['GMP Accuracy', route('ipos.gmp-accuracy')]]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('ipos.gmp') }}">IPO GMP</a> <x-icon name="chevron-right" :size="13" />
            <span>GMP Accuracy</span>
        </nav>
        <h1>IPO GMP Accuracy Tracker</h1>
        <p class="lead">Is the grey market premium a good guide to the listing price? We compare the last GMP before listing with the price each IPO actually opened at on the exchange{{ $s['count'] ? ', for '.$s['count'].' IPOs since '.$accuracy['since'] : '' }}.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        @if (! $s['count'])
            <div class="note info"><x-icon name="info" /><span>Listing prices are still being collected. This page fills in as IPOs list.</span></div>
        @else
            <div class="card">
                <div class="facts-grid">
                    <div class="fact"><span><x-icon name="layers" :size="14" /> IPOs compared</span><b>{{ number_format($s['count']) }}</b></div>
                    <div class="fact"><span><x-icon name="target" :size="14" /> Typical miss (median)</span><b>{{ number_format($s['median_abs_error'], 1) }} <small>pts</small></b></div>
                    <div class="fact"><span><x-icon name="check-circle" :size="14" /> Within ±5 points</span><b>{{ $share($s['within5']) }}</b></div>
                    <div class="fact"><span><x-icon name="check" :size="14" /> Within ±10 points</span><b>{{ $share($s['within10']) }}</b></div>
                    <div class="fact"><span><x-icon name="trending-up" :size="14" /> Direction right</span><b>{{ $share($s['direction']) }}</b></div>
                    <div class="fact"><span><x-icon name="trending-down" :size="14" /> GMP too high</span><b>{{ $share($s['over']) }} <small>of IPOs</small></b></div>
                    <div class="fact"><span><x-icon name="scale" :size="14" /> Average bias</span><b class="{{ $trend($s['mean_error']) }}">{{ $pts($s['mean_error']) }}</b></div>
                    <div class="fact"><span><x-icon name="columns" :size="14" /> Mainboard vs SME miss</span><b>{{ number_format((float) $main['median_abs_error'], 1) }} <small>vs</small> {{ number_format((float) $sme['median_abs_error'], 1) }} <small>pts</small></b></div>
                </div>
            </div>

            <div class="card card-pad prose">
                <h2 style="margin-top:0">What the numbers say</h2>
                <ul>
                    @if ($main['count'])
                        <li><strong>Mainboard GMPs have been fairly reliable.</strong> The median mainboard listing landed {{ number_format($main['median_abs_error'], 1) }} points from the GMP estimate, {{ $share($main['within10']) }} were within 10 points and the direction was right {{ $share($main['direction']) }} of the time.</li>
                    @endif
                    @if ($sme['count'])
                        <li><strong>SME GMPs are a weaker guide.</strong> The median SME miss was {{ number_format($sme['median_abs_error'], 1) }} points and only {{ $share($sme['within10']) }} were within 10 points.</li>
                    @endif
                    <li><strong>{{ $s['mean_error'] >= 0 ? 'Listings came in slightly above the GMP on average' : 'Listings came in slightly below the GMP on average' }}</strong> ({{ $pts($s['mean_error']) }}), and the GMP was too optimistic for {{ $share($s['over']) }} of IPOs, so it has not been consistently biased either way.</li>
                    @if ($negative)
                        <li><strong>A weak GMP has usually meant a weak listing.</strong> IPOs with a zero or negative GMP opened {{ $pct($negative['actual']) }} on average.</li>
                    @endif
                    @if ($high)
                        <li><strong>Very high GMPs were mostly confirmed.</strong> When the GMP pointed to more than 50%, the average listing gain was {{ $pct($high['actual']) }}.</li>
                    @endif
                </ul>
                <p class="muted" style="font-size:13.5px;margin-bottom:0">A "point" is one percentage point of the issue price. If the GMP pointed to a 20% gain and the IPO listed 26% higher, the miss is +6 points.</p>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2 class="card-title" id="by-gmp"><span class="ico"><x-icon name="bar-chart" :size="16" /></span> GMP estimate vs actual listing gain</h2>
                    <span class="card-sub">Average for each GMP level</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>GMP pointed to</th><th class="r">IPOs</th><th style="min-width:220px">GMP estimate vs actual</th><th class="r">Listed at premium</th></tr></thead>
                        <tbody>
                            @foreach ($bands as $band)
                                <tr>
                                    <td><b>{{ $band['label'] }}</b></td>
                                    <td class="r">{{ $band['count'] }}</td>
                                    <td>
                                        @foreach ([['GMP', $band['expected'], 'var(--gold)'], ['Actual', $band['actual'], $band['actual'] < 0 ? 'var(--red)' : 'var(--green)']] as [$label, $value, $color])
                                            <div style="display:grid;grid-template-columns:52px 1fr 64px;align-items:center;gap:8px;font-size:12.5px;margin:2px 0">
                                                <span class="muted">{{ $label }}</span>
                                                <span style="height:8px;border-radius:999px;background:var(--surface-3);overflow:hidden"><i style="display:block;height:100%;width:{{ round(abs((float) $value) / $maxBand * 100, 1) }}%;background:{{ $color }};border-radius:999px"></i></span>
                                                <b class="{{ $trend($value) }}" style="text-align:right">{{ $pct($value) }}</b>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td class="r">{{ $share($band['premium_share']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="report-grid">
                <div class="card">
                    <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon name="columns" :size="16" /></span> Mainboard vs SME</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Board</th><th class="r">IPOs</th><th class="r">Typical miss</th><th class="r">Within 10 pts</th><th class="r">Direction</th></tr></thead>
                            <tbody>
                                @foreach ($accuracy['boards'] as $board => $row)
                                    <tr>
                                        <td><b>{{ $board }}</b></td>
                                        <td class="r">{{ $row['count'] }}</td>
                                        <td class="r">{{ $row['median_abs_error'] === null ? '—' : number_format($row['median_abs_error'], 1).' pts' }}</td>
                                        <td class="r">{{ $share($row['within10']) }}</td>
                                        <td class="r">{{ $share($row['direction']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon name="calendar" :size="16" /></span> Month by month</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Listing month</th><th class="r">IPOs</th><th class="r">Typical miss</th><th class="r">Within 10 pts</th><th class="r">Direction</th></tr></thead>
                            <tbody>
                                @foreach ($accuracy['months'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="r">{{ $row['count'] }}</td>
                                        <td class="r">{{ $row['median_abs_error'] === null ? '—' : number_format($row['median_abs_error'], 1).' pts' }}</td>
                                        <td class="r">{{ $share($row['within10']) }}</td>
                                        <td class="r">{{ $share($row['direction']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @foreach ([['recent', 'Latest listings: GMP vs actual', 'clock'], ['misses', 'Biggest misses', 'alert']] as [$key, $title, $icon])
                <div class="card">
                    <div class="card-head"><h2 class="card-title" id="{{ $key }}"><span class="ico"><x-icon :name="$icon" :size="16" /></span> {{ $title }}</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>IPO</th><th class="r">Issue price</th><th class="r">Last GMP</th><th class="r">GMP estimate</th><th class="r">Listed at</th><th class="r">Miss</th></tr></thead>
                            <tbody>
                                @foreach ($accuracy[$key] as $row)
                                    <tr>
                                        <td class="company"><a class="co-name" href="{{ $row['url'] }}">{{ $row['name'] }}</a><small class="muted" style="display:block">{{ $row['type'] }} · listed {{ $row['date'] }}</small></td>
                                        <td class="r">{{ Ipo::money($row['price']) }}</td>
                                        <td class="r">{{ ($row['gmp'] < 0 ? '-₹' : '₹').Ipo::num(abs($row['gmp'])) }}</td>
                                        <td class="r">{{ Ipo::money($row['expected_price']) }} <small class="muted" style="display:block">{{ $pct($row['expected']) }}</small></td>
                                        <td class="r"><b>{{ Ipo::money($row['listing_price']) }}</b> <small class="{{ $trend($row['actual']) }}" style="display:block">{{ $pct($row['actual']) }}</small></td>
                                        <td class="r"><b class="{{ abs($row['error']) <= 5 ? 'up' : (abs($row['error']) <= 10 ? '' : 'down') }}">{{ $pts($row['error']) }}</b></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif

        <div class="note info">
            <x-icon name="info" />
            <span><strong>How we measure.</strong> For each IPO we take the last daily GMP recorded before listing day (for older IPOs, the final GMP reported) and add it to the upper price band to get the estimated listing price. The actual listing price is the first trade on listing day on NSE, or BSE for issues listed only there, from the exchanges' price files. IPOs whose recorded issue price looks wrong are left out. SME listings are capped at 90% above the issue price on the first day, so very high SME GMPs can't be fully reached. GMP is unofficial and past accuracy doesn't guarantee future accuracy. See the <a class="link" href="{{ route('ipos.gmp') }}">live IPO GMP</a> and the <a class="link" href="{{ route('ipos.report-card') }}">IPO report card</a>.</span>
        </div>

        <x-faq :faqs="$faqs" title="IPO GMP accuracy: FAQs" />
    </div>
</div>
@endsection
