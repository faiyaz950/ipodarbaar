@extends('layouts.app')

@php
    use App\Models\Ipo;
    $pct = fn (?float $value): string => $value === null ? '—' : ($value > 0 ? '+' : '').number_format($value, 2).'%';
    $trend = fn (?float $value): string => $value === null ? 'flat' : ($value > 0 ? 'up' : ($value < 0 ? 'down' : 'flat'));
@endphp

@section('title', 'IPO Report Card: Year-wise IPO Performance in India')
@section('description', 'Year-wise IPO report card for India: number of IPOs, funds raised, average and median listing gains and share of IPOs listing at a premium.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO Report Card</span></nav>
        <h1>IPO Report Card</h1>
        <p class="lead">How India's IPOs performed each year: how many came, how much they raised and how they listed.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th class="r">IPOs</th>
                            <th class="r">Mainboard / SME</th>
                            <th class="r">Funds raised</th>
                            <th class="r">Avg. listing gain</th>
                            <th class="r">Listed at premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($years as $year)
                            <tr>
                                <td><a class="co-name" href="{{ route('ipos.year', $year['year']) }}">IPOs in {{ $year['year'] }}</a></td>
                                <td class="r"><b>{{ number_format($year['count']) }}</b></td>
                                <td class="r">{{ $year['mainboard'] }} / {{ $year['sme'] }}</td>
                                <td class="r">{{ $year['raised'] ? '₹'.Ipo::num($year['raised'], 0).' Cr' : '—' }}</td>
                                <td class="r"><b class="{{ $trend($year['avg_gain']) }}">{{ $pct($year['avg_gain']) }}</b>@if ($year['with_listing'])<small class="muted" style="display:block">{{ $year['with_listing'] }} listed</small>@endif</td>
                                <td class="r">{{ $year['positive_share'] === null ? '—' : number_format($year['positive_share'], 1).'%' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="table-empty">No IPO data yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="note info">
            <x-icon name="info" />
            <span>Listing gains compare the listing-day price with the upper price band, using only IPOs with a recorded listing price. Past performance doesn't indicate future returns. See also how close the grey market came on each listing in the <a class="link" href="{{ route('ipos.gmp-accuracy') }}">GMP accuracy tracker</a>.</span>
        </div>
    </div>
</div>
@endsection
