@extends('layouts.app')

@section('title', 'IPO Calendar '.$month->format('F Y'))
@section('description', 'IPO calendar for '.$month->format('F Y').': opening, closing and listing dates of all mainboard and SME IPOs in India.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO Calendar</span></nav>
        <h1>IPO Calendar · {{ $month->format('F Y') }}</h1>
        <p class="lead">{{ $ipoCount }} IPOs with opening, closing or listing dates this month. Click any IPO to see full details.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card">
            <div class="card-head">
                <div class="cal-nav">
                    <a class="sq-btn" href="{{ route('ipos.calendar', ['month' => $prev]) }}" aria-label="Previous month"><x-icon name="chevron-left" /></a>
                    <h2>{{ $month->format('F Y') }}</h2>
                    <a class="sq-btn" href="{{ route('ipos.calendar', ['month' => $next]) }}" aria-label="Next month"><x-icon name="chevron-right" /></a>
                    @unless ($month->isSameMonth(now()))
                        <a class="btn btn-outline btn-sm" href="{{ route('ipos.calendar') }}">Today</a>
                    @endunless
                </div>
                <div class="legend">
                    <span><i style="background:var(--green)"></i>Opens</span>
                    <span><i style="background:var(--amber)"></i>Closes</span>
                    <span><i style="background:var(--blue)"></i>Lists</span>
                </div>
            </div>
            <div class="cal-wrap">
                <table class="cal">
                    <thead><tr>@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($weeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="{{ $day['inMonth'] ? '' : 'out' }} {{ $day['today'] ? 'today' : '' }}">
                                        <span class="dn">{{ $day['date']->format('j') }}</span>
                                        @foreach (array_slice($day['events'], 0, 4) as $ev)
                                            <a class="ev ev-{{ $ev['kind'] }}" href="{{ $ev['ipo']->url() }}" title="{{ $ev['ipo']->name }}: {{ $ev['label'] }}">
                                                <b>{{ $ev['label'] }}</b><span>{{ $ev['ipo']->name }}</span>
                                            </a>
                                        @endforeach
                                        @if (count($day['events']) > 4)
                                            <span class="more">+{{ count($day['events']) - 4 }} more</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-title"><span class="ico"><x-icon name="calendar" :size="16" /></span> Day-by-day agenda</div>
            </div>
            @forelse ($agenda as $date => $events)
                @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                <div class="agenda-day" @if($d->isToday()) style="background:var(--gold-soft)" @endif>
                    <div class="agenda-date"><b>{{ $d->format('j') }}</b><span>{{ $d->format('D') }}</span></div>
                    <div class="agenda-list">
                        @foreach ($events as $ev)
                            <div class="agenda-item">
                                <span class="badge {{ ['open_date' => 'b-open', 'close_date' => 'b-closed', 'listing_date' => 'b-upcoming'][$ev['kind']] }}" style="min-width:58px;justify-content:center">{{ $ev['label'] }}</span>
                                <a class="co-name" href="{{ $ev['ipo']->url() }}">{{ $ev['ipo']->name }}</a>
                                <span class="badge b-{{ $ev['ipo']->type }}">{{ $ev['ipo']->typeLabel() }}</span>
                                <span class="muted hide-sm" style="font-size:13px">{{ $ev['ipo']->priceBand() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty">
                    <div class="table-empty"><div class="ico"><x-icon name="calendar" :size="22" /></div>No IPO events in {{ $month->format('F Y') }}.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
