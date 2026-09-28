@extends('layouts.app')

@section('title', 'IPO Guide: Learn How IPOs Work in India (IPO Academy)')
@section('description', 'Free IPO guides for Indian investors: how to apply for an IPO, what GMP means, how allotment works and how to check it, SME vs mainboard IPOs and IPO tax.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO Guide', route('guides.index')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'IPO Guide',
    'url' => route('guides.index'),
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => collect($guides)->keys()->values()->map(fn (string $slug, int $i): array => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => route('guides.show', $slug),
            'name' => $guides[$slug]['h1'],
        ])->all(),
    ],
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO Guide</span></nav>
        <h1>IPO Guide: Learn How IPOs Work</h1>
        <p class="lead">Clear, up-to-date explainers for Indian investors, from applying for your first IPO to checking allotment and paying tax on listing gains.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="grid-3">
            @foreach ($guides as $slug => $guide)
                <a class="calc-card" href="{{ route('guides.show', $slug) }}">
                    <span class="ico"><x-icon :name="$guide['icon']" :size="22" /></span>
                    <h2 style="font-size:16px;font-weight:700">{{ $guide['h1'] }}</h2>
                    <p>{{ $guide['summary'] }}</p>
                    <span class="go">{{ $guide['minutes'] }} min read <x-icon name="arrow-right" :size="14" /></span>
                </a>
            @endforeach
        </div>

        <nav class="card card-pad" aria-label="IPO lists">
            <h2 class="card-title" style="margin-bottom:12px">Put it into practice</h2>
            <div class="hub-links">
                <a class="chip" href="{{ route('ipos.current') }}">Current IPOs</a>
                <a class="chip" href="{{ route('ipos.upcoming') }}">Upcoming IPOs</a>
                <a class="chip" href="{{ route('ipos.gmp') }}">IPO GMP Today</a>
                <a class="chip" href="{{ route('ipos.allotment') }}">IPO Allotment Status</a>
                <a class="chip" href="{{ route('calculators.index') }}">IPO Calculators</a>
            </div>
        </nav>
    </div>
</div>
@endsection
