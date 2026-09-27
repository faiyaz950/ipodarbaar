@extends('layouts.app')

@section('title', $calc['name'])
@section('description', $calc['short'].' '.\Illuminate\Support\Str::limit($calc['about'], 100))

@push('head')
<x-jsonld :breadcrumbs="[
    ['Home', route('home')],
    ['Calculators', route('calculators.index')],
    [$calc['name'], route('calculators.show', $calc['slug'])],
]" />
@if (! empty($calc['faqs']))
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn (array $faq): array => [
        '@type' => 'Question',
        'name' => $faq[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq[1])],
    ], $calc['faqs']),
]" />
@endif
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
                <div class="card-title" style="margin-bottom:12px"><span class="ico"><x-icon name="info" :size="16" /></span> How it works</div>
                <p class="prose" style="font-size:15px">{{ $calc['about'] }}</p>
            </div>
            <div class="card card-pad">
                <div class="card-title" style="margin-bottom:12px"><span class="ico"><x-icon name="calculator" :size="16" /></span> Formula</div>
                <div class="formula">{{ $calc['formula'] }}</div>
                <div class="note" style="margin-top:14px">
                    <x-icon name="alert" />
                    <span>Results are estimates for illustration only and not financial advice.</span>
                </div>
            </div>
        </div>

        @if (! empty($calc['faqs']))
            <div class="card">
                <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="message" :size="16" /></span> Frequently asked questions</div></div>
                <div class="faq" style="border-top:0">
                    @foreach ($calc['faqs'] as [$q, $a])
                        <details @if($loop->first) open @endif>
                            <summary>{{ $q }} <x-icon name="chevron-down" :size="18" /></summary>
                            <p>{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif

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
