@extends('layouts.app')

@section('title', 'Free Financial Calculators: IPO, SIP, EMI, Tax & F&O')
@section('description', 'Free online calculators: IPO GMP and profit, SIP, EMI, home loan, FD, PPF, retirement, income tax, capital gains, F&O margin, option premium and brokerage.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Calculators</span></nav>
        <h1>Calculators</h1>
        <p class="lead">{{ count(\App\Support\Calculators::all()) }} free tools to plan IPO applications, estimate returns, compare savings and work out tax, with instant results as you type.</p>
    </div>
</section>

<div class="page-body">
    <div class="container" data-calc-filter>
        <div class="calc-toolbar">
            <label class="input-icon calc-search">
                <x-icon name="search" :size="17" />
                <span class="sr-only">Search calculators</span>
                <input class="input" type="search" placeholder="Search calculators, e.g. GMP, SIP, tax" data-calc-search autocomplete="off">
            </label>
            <div class="hub-links">
                <a class="chip" href="{{ route('portfolio') }}"><x-icon name="briefcase" :size="14" /> IPO Portfolio Tracker</a>
                <a class="chip" href="{{ route('ipos.sme-dashboard') }}"><x-icon name="bar-chart" :size="14" /> SME IPO Dashboard</a>
                <a class="chip" href="{{ route('compare') }}"><x-icon name="columns" :size="14" /> Compare IPOs</a>
            </div>
        </div>
        <div class="card card-pad muted" data-calc-empty hidden>No calculator matches your search.</div>
        @foreach (\App\Support\Calculators::GROUPS as $key => $label)
            @continue(empty($groups[$key]))
            <section style="margin-bottom:36px" data-calc-group>
                <div class="section-head" style="margin-bottom:16px">
                    <div>
                        <span class="eyebrow">{{ count($groups[$key]) }} tools</span>
                        <h2 class="section-title" style="font-size:24px">{{ $label }}</h2>
                    </div>
                </div>
                <div class="grid-4">
                    @foreach ($groups[$key] as $calc)
                        <a class="calc-card g-{{ $calc['group'] }}" href="{{ route('calculators.show', $calc['slug']) }}" data-calc-card="{{ strtolower($calc['name'].' '.$calc['short']) }}">
                            <span class="ico"><x-icon :name="$calc['icon']" :size="22" /></span>
                            <h3>{{ $calc['name'] }}</h3>
                            <p>{{ $calc['short'] }}</p>
                            <span class="go">Open calculator <x-icon name="arrow-right" :size="14" /></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
