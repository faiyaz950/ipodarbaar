@extends('layouts.app')

@php
    use App\Models\Ipo;
    $year = $stats['year'];
    $pct = fn (?float $value): string => $value === null ? '—' : ($value > 0 ? '+' : '').number_format($value, 2).'%';
    $trend = fn (?float $value): string => $value === null ? 'flat' : ($value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat'));
    $maxMonth = max(1, max(array_column($stats['months'], 'count')));
    $lists = [
        ['title' => 'Top listing gains', 'icon' => 'trophy', 'rows' => $stats['best'], 'metric' => 'gain'],
        ['title' => 'Weakest listings', 'icon' => 'trending-down', 'rows' => $stats['worst'], 'metric' => 'gain'],
        ['title' => 'Largest issues', 'icon' => 'coins', 'rows' => $stats['largest'], 'metric' => 'issue_size'],
    ];
@endphp

@section('title', "IPO List {$year}: All {$stats['count']} IPOs in India with Listing Gains")
@section('description', "IPO list {$year}: all {$stats['count']} mainboard and SME IPOs in India with dates, price band, funds raised, listing gains, best and worst listings and month-wise count.")

@push('head')
<x-jsonld :breadcrumbs="[
    ['Home', route('home')],
    ['IPO Report Card', route('ipos.report-card')],
    ['IPOs in '.$year, route('ipos.year', $year)],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('ipos.report-card') }}">IPO Report Card</a> <x-icon name="chevron-right" :size="13" /> <span>{{ $year }}</span></nav>
        <h1>IPO List {{ $year }}: All IPOs in {{ $year }}</h1>
        <p class="lead">{{ $stats['count'] }} IPOs ({{ $stats['mainboard'] }} mainboard, {{ $stats['sme'] }} SME) raised {{ $stats['raised'] ? '₹'.Ipo::num($stats['raised'], 0).' crore' : 'an undisclosed amount' }}.</p>
        @if (count($years) > 1)
            <div class="chips year-chips">
                @foreach ($years as $y)
                    <a class="chip {{ $y === $year ? 'active' : '' }}" href="{{ route('ipos.year', $y) }}">{{ $y }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="facts-grid">
                <div class="fact"><span><x-icon name="layers" :size="14" /> Total IPOs</span><b>{{ number_format($stats['count']) }}</b></div>
                <div class="fact"><span><x-icon name="coins" :size="14" /> Funds raised</span><b>{{ $stats['raised'] ? '₹'.Ipo::num($stats['raised'], 0).' Cr' : '—' }}</b></div>
                <div class="fact"><span><x-icon name="building" :size="14" /> Mainboard raised</span><b>{{ $stats['raised_mainboard'] ? '₹'.Ipo::num($stats['raised_mainboard'], 0).' Cr' : '—' }}</b></div>
                <div class="fact"><span><x-icon name="briefcase" :size="14" /> SME raised</span><b>{{ $stats['raised_sme'] ? '₹'.Ipo::num($stats['raised_sme'], 0).' Cr' : '—' }}</b></div>
                <div class="fact"><span><x-icon name="trending-up" :size="14" /> Average listing gain</span><b class="{{ $trend($stats['avg_gain']) }}">{{ $pct($stats['avg_gain']) }}</b></div>
                <div class="fact"><span><x-icon name="activity" :size="14" /> Median listing gain</span><b class="{{ $trend($stats['median_gain']) }}">{{ $pct($stats['median_gain']) }}</b></div>
                <div class="fact"><span><x-icon name="check-circle" :size="14" /> Listed at a premium</span><b>{{ $stats['positive_share'] === null ? '—' : number_format($stats['positive_share'], 1).'%' }}</b></div>
                <div class="fact"><span><x-icon name="info" :size="14" /> Listing data</span><b>{{ $stats['with_listing'] }} <small>of {{ $stats['count'] }} IPOs</small></b></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title" id="by-month"><span class="ico"><x-icon name="bar-chart" :size="16" /></span> IPOs by month in {{ $year }}</h2>
                <span class="card-sub">By issue open date</span>
            </div>
            <div class="fin-chart month-chart" role="img" aria-label="Number of IPOs opening each month of {{ $year }}">
                @foreach ($stats['months'] as $month => $data)
                    <div class="fin-col" title="{{ $data['count'] }} IPOs · ₹{{ Ipo::num($data['raised'], 0) }} Cr">
                        <div class="fin-bars">
                            <b class="fin-val">{{ $data['count'] ?: '' }}</b>
                            <i class="rev" style="height: {{ round($data['count'] / $maxMonth * 100, 1) }}%"></i>
                        </div>
                        <span class="fin-label">{{ \Illuminate\Support\Carbon::create($year, $month)->format('M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($stats['with_listing'] === 0)
            <div class="note info"><x-icon name="info" /><span>Listing prices haven't been recorded for {{ $year }} IPOs yet, so listing-gain rankings will appear once they are added.</span></div>
        @endif

        <div class="report-grid">
            @foreach ($lists as $list)
                @continue(empty($list['rows']))
                <div class="card">
                    <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon :name="$list['icon']" :size="16" /></span> {{ $list['title'] }}</h2></div>
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                @foreach ($list['rows'] as $row)
                                    <tr>
                                        <td><a href="{{ $row['url'] }}" class="co-name">{{ $row['name'] }}</a><small class="muted" style="display:block">{{ $row['type'] }}{{ $row['date'] ? ' · '.$row['date'] : '' }}</small></td>
                                        <td class="r">
                                            @if ($list['metric'] === 'gain')
                                                <b class="{{ $trend($row['gain']) }}">{{ $pct($row['gain']) }}</b>
                                            @else
                                                <b>₹{{ Ipo::num($row['issue_size'], 0) }} Cr</b>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title" id="ipo-list"><span class="ico"><x-icon name="layers" :size="16" /></span> IPO List {{ $year }}: All IPOs</h2></div>
            <x-ipo-table :ipos="$ipos" />
            @if ($ipos->hasPages())
                <div style="border-top: 1px solid var(--border)">{{ $ipos->links() }}</div>
            @endif
        </div>

        <div class="note info">
            <x-icon name="info" />
            <span>Listing gains compare the listing-day price with the upper price band and are based on {{ $stats['with_listing'] }} IPOs with a recorded listing price. Past listings don't indicate future returns.</span>
        </div>
    </div>
</div>
@endsection
