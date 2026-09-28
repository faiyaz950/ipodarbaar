@extends('layouts.app')

@php
    [$seoTitle, $howSteps] = \App\Support\Calculators::SEO[$calc['slug']] ?? [$calc['name'], []];
    $calcUrl = route('calculators.show', $calc['slug']);
    $calcFaqs = array_merge($calc['faqs'] ?? [], [
        ['Is the '.$calc['name'].' free to use?', 'Yes. The '.$calc['name'].' on IPO Darbaar is free, needs no sign-up and works on mobile and desktop.'],
        ['Is my data saved anywhere?', 'No. All calculations run in your browser; the numbers you enter are not sent to or stored on our servers.'],
    ]);
@endphp

@section('title_full', $seoTitle)
@section('description', \Illuminate\Support\Str::limit($calc['short'].' Free online '.$calc['name'].' with formula, step-by-step guide and FAQs. No sign-up.', 158))

@push('head')
<x-jsonld :breadcrumbs="[
    ['Home', route('home')],
    ['Calculators', route('calculators.index')],
    [$calc['name'], $calcUrl],
]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => $calc['name'],
    'url' => $calcUrl,
    'description' => $calc['short'],
    'applicationCategory' => 'FinanceApplication',
    'operatingSystem' => 'Any (web browser)',
    'browserRequirements' => 'Requires JavaScript',
    'inLanguage' => 'en-IN',
    'isAccessibleForFree' => true,
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR'],
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home')],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('calculators.index') }}">Calculators</a> <x-icon name="chevron-right" :size="13" />
            <span>{{ $calc['name'] }}</span>
        </nav>
        <h1>{{ $calc['name'] }}</h1>
        <p class="lead">{{ $calc['short'] }}</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="card" data-calc="{{ $calc['slug'] }}">
            <div class="calc-layout">
                <div class="calc-inputs" data-inputs>
                    <noscript><p class="muted">Please enable JavaScript to use this calculator.</p></noscript>
                </div>
                <div class="calc-results" data-results></div>
            </div>
        </div>

        <x-ad-slot name="calculator" />

        <div class="grid-2">
            <div class="card card-pad">
                <h2 class="card-title" style="margin-bottom:12px" id="how-it-works"><span class="ico"><x-icon name="info" :size="16" /></span> How the {{ $calc['name'] }} works</h2>
                <p class="prose" style="font-size:15px">{{ $calc['about'] }}</p>
            </div>
            <div class="card card-pad">
                <h2 class="card-title" style="margin-bottom:12px" id="formula"><span class="ico"><x-icon name="calculator" :size="16" /></span> {{ $calc['name'] }} formula</h2>
                <div class="formula">{{ $calc['formula'] }}</div>
                <div class="note" style="margin-top:14px">
                    <x-icon name="alert" />
                    <span>Results are estimates for illustration only and not financial advice.</span>
                </div>
            </div>
        </div>

        @if ($howSteps)
            <div class="card card-pad">
                <h2 class="card-title" style="margin-bottom:12px" id="how-to-use"><span class="ico"><x-icon name="check-circle" :size="16" /></span> How to use the {{ $calc['name'] }}</h2>
                <ol class="prose" style="font-size:15px;margin:0">
                    @foreach ($howSteps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
                @if ($calc['group'] === 'ipo')
                    <p class="muted" style="font-size:14px;margin-top:12px">Need live numbers? Check <a class="link" href="{{ route('ipos.gmp') }}">IPO GMP today</a>, <a class="link" href="{{ route('ipos.current') }}">current IPOs</a> or the <a class="link" href="{{ route('guides.show', 'how-ipo-allotment-works') }}">IPO allotment guide</a>.</p>
                @endif
            </div>
        @endif

        <x-faq :faqs="$calcFaqs" :title="$calc['name'].': FAQs'" />

        @if ($related->isNotEmpty())
            <div>
                <div class="section-head" style="margin-bottom:14px">
                    <h2 class="section-title" style="font-size:22px">More {{ \App\Support\Calculators::GROUPS[$calc['group']] }}</h2>
                    <a href="{{ route('calculators.index') }}" class="link-gold">All calculators <x-icon name="arrow-right" :size="14" /></a>
                </div>
                <div class="grid-4">
                    @foreach ($related->take(4) as $r)
                        <a class="calc-card g-{{ $r['group'] }}" href="{{ route('calculators.show', $r['slug']) }}">
                            <span class="ico"><x-icon :name="$r['icon']" :size="22" /></span>
                            <h3>{{ $r['name'] }}</h3>
                            <p>{{ $r['short'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/calculators.js') }}?v={{ filemtime(public_path('assets/js/calculators.js')) }}" defer></script>
@endpush
