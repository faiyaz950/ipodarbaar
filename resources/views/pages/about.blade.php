@extends('layouts.app')

@section('title', 'About IPO Darbaar: Our Data, Methods & Editorial Policy')
@section('description', 'What IPO Darbaar covers, where our IPO and GMP data comes from, how often it updates, our editorial standards, corrections policy and how to contact us.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['About', route('about')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'name' => 'About IPO Darbaar',
    'url' => route('about'),
    'mainEntity' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home'), 'logo' => asset('icons/icon-512.png')],
]" />
@endpush

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
            <p><strong>IPO Darbaar</strong> brings together everything an investor needs to follow India's primary market: every mainboard and SME IPO, live grey market premium (GMP), subscription, allotment and listing dates, an IPO calendar, short market news in English and Hinglish, and practical calculators.</p>

            <h2 id="what-we-cover">What we cover</h2>
            <ul>
                <li><strong>IPO pages</strong> for every issue: price band, lot size, minimum investment, issue structure, financials, key dates on the T+3 timeline, GMP trend, allotment and listing details.</li>
                <li><strong>Live lists</strong> of <a href="{{ route('ipos.current') }}">current</a>, <a href="{{ route('ipos.upcoming') }}">upcoming</a> and <a href="{{ route('ipos.listed') }}">recently listed</a> IPOs, <a href="{{ route('ipos.gmp') }}">IPO GMP today</a> and <a href="{{ route('ipos.allotment') }}">allotment status</a> with registrar links.</li>
                <li><strong>News and Shorts</strong> on IPOs, stocks, commodities, crypto and personal finance.</li>
                <li><strong><a href="{{ route('calculators.index') }}">Calculators</a></strong> and <strong><a href="{{ route('guides.index') }}">IPO guides</a></strong> that explain how IPOs, allotment and taxes work.</li>
            </ul>

            <h2 id="data">Where our data comes from</h2>
            <p>IPO details (dates, price band, issue size, lot size) come from our data partner, which compiles them from offer documents (DRHP and RHP) and exchange announcements. Our editors add and correct details such as lot size, subscription figures, registrar, financials and listing price. Allotment and listing dates after an issue closes are calculated from SEBI's T+3 timeline and marked "tentative" until confirmed. Grey market premium also comes from our data partner; it is unofficial by nature and we always label it as indicative.</p>
            <p>IPO data refreshes automatically every 15 minutes during market days, and every page shows when it was last updated.</p>

            <h2 id="editorial-policy">Editorial standards</h2>
            <ul>
                <li><strong>Accuracy first:</strong> figures come from primary documents wherever possible, and estimates are clearly labelled.</li>
                <li><strong>No recommendations:</strong> we do not tell readers to apply for or avoid any IPO. Polls show visitor opinion only.</li>
                <li><strong>Independence:</strong> no company pays to be listed or to change what we publish. Sponsored or affiliate links are always labelled "Sponsored".</li>
                <li><strong>Clarity:</strong> guides are written in plain language, dated, and reviewed when rules change.</li>
            </ul>
            <p>Read the full <a href="{{ route('editorial-policy') }}">editorial policy</a>.</p>

            <h2 id="corrections">Corrections</h2>
            <p>If you spot an error, <a href="{{ route('contact') }}">tell us</a> and we will correct it promptly and update the page's date. Significant corrections to guides are noted on the page.</p>

            <h2 id="contact">Contact</h2>
            @if (config('mail.from.address'))
                <p>Email us at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a> for corrections, feedback or partnership enquiries.</p>
            @else
                <p>Reach us through our official channels for corrections, feedback or partnership enquiries.</p>
            @endif

            <h2 id="disclaimer">Important</h2>
            <p>IPO Darbaar is an information and education platform and is not registered with SEBI as an investment adviser or research analyst. Please read our <a href="{{ route('disclaimer') }}">disclaimer</a> and <a href="{{ route('privacy') }}">privacy policy</a>.</p>
        </div>
    </div>
</div>
@endsection
