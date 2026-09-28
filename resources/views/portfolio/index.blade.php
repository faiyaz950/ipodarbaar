@extends('layouts.app')

@php
    $faqs = [
        ['Where is my portfolio saved?', 'Only in this browser on this device. Nothing is sent to IPO Darbaar except the names of the IPOs, to fetch their latest price, GMP and listing price. Use "Download backup" to keep a copy or move it to another device.'],
        ['How is my profit worked out?', 'For shares you have sold, profit is the selling price minus the issue price. For shares you still hold, the listing price is used once it is available; before listing, the latest GMP gives an estimate, marked "est.". Charges and tax are not included; use the IPO net proceeds calculator for those.'],
        ['What is the allotment rate?', 'The share of your applications with a known result that got an allotment. Applications still awaiting allotment are not counted.'],
        ['Do I need an account?', 'No. There is no sign-up. Clearing your browser data removes the portfolio, so download a backup from time to time.'],
    ];
@endphp

@section('title', 'IPO Portfolio Tracker: Track Applications, Allotment & Profit')
@section('description', 'Free IPO portfolio tracker: record the IPOs you applied for, your allotment and selling price, and see your allotment rate and profit. No sign-up; saved on your device.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['Calculators', route('calculators.index')], ['IPO Portfolio Tracker', route('portfolio')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'IPO Portfolio Tracker',
    'url' => route('portfolio'),
    'applicationCategory' => 'FinanceApplication',
    'operatingSystem' => 'Any',
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('calculators.index') }}">Calculators</a> <x-icon name="chevron-right" :size="13" /> <span>Portfolio Tracker</span></nav>
        <h1>IPO Portfolio Tracker</h1>
        <p class="lead">Record the IPOs you apply for, whether you got an allotment and what you sold at. See your allotment rate and profit at a glance, with live GMP and listing prices.</p>
        <span class="updated-line"><x-icon name="shield" :size="14" /> Saved on this device only · no sign-up</span>
    </div>
</section>

<div class="page-body">
    <div class="container stack" data-portfolio data-prices="{{ route('portfolio.prices') }}" data-suggest="{{ route('ipos.suggest') }}">
        <div class="card">
            <div class="facts-grid" data-pf-summary>
                <div class="fact"><span><x-icon name="layers" :size="14" /> Applications</span><b>0</b></div>
                <div class="fact"><span><x-icon name="ticket" :size="14" /> Allotment rate</span><b>—</b></div>
                <div class="fact"><span><x-icon name="wallet" :size="14" /> Invested (allotted)</span><b>₹0</b></div>
                <div class="fact"><span><x-icon name="trending-up" :size="14" /> Profit</span><b>₹0</b></div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="card-title" id="add" style="margin-bottom:14px"><span class="ico"><x-icon name="plus" :size="16" /></span> <span data-pf-form-title>Add an IPO application</span></h2>
            <form class="pf-form" data-pf-form novalidate>
                <input type="hidden" name="id">
                <input type="hidden" name="slug">
                <div class="pf-field pf-wide pf-search">
                    <label for="pf-name">IPO</label>
                    <input class="input" id="pf-name" name="name" type="text" autocomplete="off" maxlength="120" placeholder="Search an IPO, e.g. Moneyview" required>
                    <div class="pf-suggest" data-pf-suggest hidden></div>
                </div>
                <div class="pf-field">
                    <label for="pf-price">Price per share (₹)</label>
                    <input class="input" id="pf-price" name="price" type="number" inputmode="decimal" min="0.01" step="0.01" required>
                </div>
                <div class="pf-field">
                    <label for="pf-lot">Lot size (shares)</label>
                    <input class="input" id="pf-lot" name="lot" type="number" inputmode="numeric" min="1" step="1" required>
                </div>
                <div class="pf-field">
                    <label for="pf-lots">Lots applied</label>
                    <input class="input" id="pf-lots" name="lotsApplied" type="number" inputmode="numeric" min="1" step="1" value="1" required>
                </div>
                <div class="pf-field">
                    <label for="pf-result">Allotment</label>
                    <select class="input" id="pf-result" name="result">
                        <option value="pending">Awaiting allotment</option>
                        <option value="allotted">Allotted</option>
                        <option value="rejected">Not allotted</option>
                    </select>
                </div>
                <div class="pf-field" data-pf-allotted hidden>
                    <label for="pf-allotted">Lots allotted</label>
                    <input class="input" id="pf-allotted" name="lotsAllotted" type="number" inputmode="numeric" min="1" step="1" value="1">
                </div>
                <div class="pf-field" data-pf-allotted hidden>
                    <label for="pf-sold">Sold at (₹, optional)</label>
                    <input class="input" id="pf-sold" name="soldAt" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="Still holding">
                </div>
                <div class="pf-actions pf-wide">
                    <button type="submit" class="btn btn-gold" data-pf-submit><x-icon name="plus" :size="15" /> Add to portfolio</button>
                    <button type="button" class="btn btn-outline" data-pf-cancel hidden>Cancel</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="card-title" id="portfolio"><span class="ico"><x-icon name="briefcase" :size="16" /></span> My IPO Applications</h2>
                    <div class="card-sub">Profit uses your selling price, the listing price or, before listing, the latest GMP (est.)</div>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-outline btn-sm" data-pf-export><x-icon name="download" :size="14" /> Download backup</button>
                    <label class="btn btn-outline btn-sm">Restore backup<input type="file" accept="application/json,.json" data-pf-import hidden></label>
                </div>
            </div>
            <div data-pf-list>
                <div class="table-empty">Loading your portfolio…</div>
            </div>
        </div>

        <div class="card card-pad seo-copy">
            <div>
                <h2>How the IPO portfolio tracker works</h2>
                <p>Add each IPO you apply for with the price, lot size and lots applied. After the basis of allotment, mark it as allotted or not allotted; check the result on the <a class="link" href="{{ route('ipos.allotment') }}">IPO allotment status</a> page. When you sell, enter your selling price. The tracker keeps a running allotment rate and profit across all your IPOs.</p>
            </div>
            <div>
                <h2>Before you sell</h2>
                <p>The profit shown here is before charges and tax. To see the money you actually keep after STT, brokerage, DP charges and short-term capital gains tax, use the <a class="link" href="{{ route('calculators.show', 'ipo-net-proceeds') }}">IPO net proceeds calculator</a>.</p>
            </div>
        </div>

        <x-faq :faqs="$faqs" title="IPO Portfolio Tracker: FAQs" />
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/portfolio.js') }}?v={{ filemtime(public_path('assets/js/portfolio.js')) }}" defer></script>
@endpush
