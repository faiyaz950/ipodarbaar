@extends('layouts.app')

@section('title', 'Disclaimer')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Disclaimer</span></nav>
        <h1>Disclaimer</h1>
    </div>
</section>
<div class="page-body">
    <div class="container" style="max-width:900px">
        <div class="card card-pad prose" style="padding:32px">
            <p>IPO Darbaar is an information and education platform. We are <strong>not registered with SEBI</strong> as an investment adviser or research analyst, and nothing on this website constitutes investment advice or a recommendation to buy, sell or subscribe to any security.</p>
            <h3>Grey Market Premium (GMP)</h3>
            <p>GMP figures are collected from unofficial sources and are indicative only. The grey market is unregulated; GMP can change rapidly and is not a reliable predictor of listing price or performance.</p>
            <h3>Accuracy of information</h3>
            <p>While we try to keep data accurate and current, IPO details, dates and prices may change or contain errors. Tentative dates (allotment, refunds, demat credit) are estimates. Always verify with the Red Herring Prospectus (RHP), the stock exchanges and your broker.</p>
            <h3>Calculators</h3>
            <p>Calculator results are estimates based on the inputs and assumptions shown. Actual returns, charges and taxes may differ. Tax calculations are simplified and do not account for surcharge, set-offs or individual circumstances. Consult a qualified professional.</p>
            <h3>Investment risk</h3>
            <p>Investments in securities markets are subject to market risks. Read all related documents carefully before investing. Past performance does not guarantee future returns.</p>
        </div>
    </div>
</div>
@endsection
