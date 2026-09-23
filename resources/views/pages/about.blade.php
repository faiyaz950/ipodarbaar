@extends('layouts.app')

@section('title', 'About IPO Darbaar')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>About</span></nav>
        <h1>About IPO Darbaar</h1>
        <p class="lead">A royal court for every Indian IPO: clear, fast and free.</p>
    </div>
</section>
<div class="page-body">
    <div class="container" style="max-width:900px">
        <div class="card card-pad prose" style="padding:32px">
            <p><strong>IPO Darbaar</strong> brings together everything an investor needs to follow the Indian primary market: mainboard and SME IPO listings, live grey market premium (GMP), subscription and listing dates, an IPO calendar, bite-sized market news and a set of practical calculators.</p>
            <h3>What you'll find here</h3>
            <ul>
                <li><strong>IPO dashboard:</strong> open, upcoming, listing-soon and recently listed IPOs with price band, issue size and GMP.</li>
                <li><strong>IPO detail pages:</strong> key dates, T+3 timeline, lot-size investment table and GMP-based estimates.</li>
                <li><strong>News & Shorts:</strong> market stories in English and Hinglish, readable in under a minute.</li>
                <li><strong>Calculators:</strong> IPO GMP, listing profit, allotment chance, SIP, EMI, FD, PPF, brokerage, capital gains and more.</li>
            </ul>
            <h3>Data</h3>
            <p>IPO data and market news are sourced from partner feeds and refreshed automatically through the day. Dates after an issue closes are tentative estimates based on SEBI's T+3 listing timeline unless confirmed.</p>
            <p>Please read our <a href="{{ route('disclaimer') }}">disclaimer</a> before relying on any information on this site.</p>
        </div>
    </div>
</div>
@endsection
