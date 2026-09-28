@extends('layouts.app')

@section('title', 'Editorial Policy: How IPO Darbaar Collects, Checks & Corrects Data')
@section('description', 'How IPO Darbaar sources IPO data and GMP, publishes news and guides, labels ads and sponsored links, and corrects errors. We do not give investment advice.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['About', route('about')], ['Editorial Policy', route('editorial-policy')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => 'IPO Darbaar Editorial Policy',
    'url' => route('editorial-policy'),
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home')],
    'dateModified' => '2026-09-28',
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('about') }}">About</a> <x-icon name="chevron-right" :size="13" /> <span>Editorial Policy</span></nav>
        <h1>Editorial Policy</h1>
        <p class="lead">How we collect, check, publish and correct the information on IPO Darbaar.</p>
        <span class="updated-line"><x-icon name="refresh" :size="14" /> Last updated 28 Sep 2026</span>
    </div>
</section>
<div class="page-body">
    <div class="container" style="max-width:900px">
        <div class="card card-pad prose" style="padding:32px">
            <h2 id="purpose">Our purpose</h2>
            <p>IPO Darbaar helps Indian investors follow every mainboard and SME IPO with clear, up-to-date facts. We publish data, news and education. We do not tell readers what to buy, sell or apply for.</p>

            <h2 id="ipo-data">IPO data</h2>
            <ul>
                <li><strong>Source:</strong> issue details such as dates, price band and issue size come from our data partner, which compiles them from offer documents (DRHP and RHP) and stock exchange announcements. They refresh automatically through the day.</li>
                <li><strong>Editor checks:</strong> our editors add and correct details such as lot size, subscription figures, registrar, financials and listing price. Corrected fields are locked so the automatic refresh cannot overwrite them.</li>
                <li><strong>Calculated dates:</strong> allotment, refund, demat credit and listing dates after an issue closes are worked out from SEBI's T+3 timeline and marked "tentative" until confirmed.</li>
                <li><strong>Grey market premium:</strong> GMP also comes from our data partner. It is unofficial by nature, so we always label it as indicative and never present it as a forecast.</li>
            </ul>

            <h2 id="news">News</h2>
            <p>Market news and Shorts are supplied by our news partner in English and Hinglish. Our editors can correct a story, edit its headline or summary, or remove it. Each article shows its publication date, and we add links to the IPO pages of companies a story mentions.</p>

            <h2 id="guides">Guides and explainers</h2>
            <p>IPO guides are written by the IPO Darbaar Research Desk from primary sources: SEBI regulations and circulars, stock exchange rules and the Income-tax Act. Each guide shows when it was published and last updated, and we review guides when the rules change.</p>

            <h2 id="independence">Independence, ads and sponsored links</h2>
            <ul>
                <li>No company pays to be listed on IPO Darbaar or to change its data, GMP or coverage.</li>
                <li>Advertisements are labelled "Advertisement" and are kept separate from editorial content.</li>
                <li>Links to brokers that may pay us a referral fee are labelled "Sponsored" and marked as sponsored links for search engines.</li>
            </ul>

            <h2 id="no-advice">No investment advice</h2>
            <p>IPO Darbaar is not registered with SEBI as an investment adviser or research analyst. We do not publish buy, sell or "apply" ratings. Sentiment polls show what visitors think, not our view. Always read the offer document and consider your own situation, or consult a registered adviser, before investing.</p>

            <h2 id="corrections">Corrections</h2>
            <p>If something on the site is wrong, please tell us through the <a href="{{ route('contact') }}">contact page</a> with the page link and the correct information. We check it against primary sources, fix the page and update its date. Significant corrections to guides are noted on the guide.</p>

            <h2 id="changes">Changes to this policy</h2>
            <p>We update this policy when our processes change. The date at the top shows the latest version. See also our <a href="{{ route('about') }}">about page</a>, <a href="{{ route('disclaimer') }}">disclaimer</a> and <a href="{{ route('privacy') }}">privacy policy</a>.</p>
        </div>
    </div>
</div>
@endsection
