@extends('layouts.app')

@section('title', 'Financial Calculators: IPO, SIP, EMI, F&O, Income Tax & More')
@section('description', 'Free IPO GMP, SIP, retirement, home loan, EMI, FD, PPF, income tax, F&O margin, option premium (Black-Scholes), P&L, hedging, beta, brokerage and capital gains calculators.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Calculators</span></nav>
        <h1>Calculators</h1>
        <p class="lead">{{ count(\App\Support\Calculators::all()) }} free tools to plan IPO applications, estimate returns, compare savings and work out tax, with instant results as you type.</p>
    </div>
</section>

<div class="page-body">
    <div class="container">
        @foreach (\App\Support\Calculators::GROUPS as $key => $label)
            @continue(empty($groups[$key]))
            <section style="margin-bottom:36px">
                <div class="section-head" style="margin-bottom:16px">
                    <div>
                        <span class="eyebrow">{{ count($groups[$key]) }} tools</span>
                        <h2 class="section-title" style="font-size:24px">{{ $label }}</h2>
                    </div>
                </div>
                <div class="grid-4">
                    @foreach ($groups[$key] as $calc)
                        <a class="calc-card" href="{{ route('calculators.show', $calc['slug']) }}">
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
