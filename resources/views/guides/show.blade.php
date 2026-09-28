@extends('layouts.app')

@php
    $url = route('guides.show', $guide['slug']);
    $published = \Illuminate\Support\Carbon::parse($guide['published']);
    $updated = \Illuminate\Support\Carbon::parse($guide['updated']);
@endphp

@section('title_full', $guide['title'])
@section('description', $guide['description'])
@section('og_type', 'article')

@push('head')
<meta property="article:published_time" content="{{ $published->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $updated->toIso8601String() }}">
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO Guide', route('guides.index')], [$guide['h1'], $url]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $guide['h1'],
    'description' => $guide['description'],
    'url' => $url,
    'mainEntityOfPage' => $url,
    'inLanguage' => 'en-IN',
    'datePublished' => $published->toIso8601String(),
    'dateModified' => $updated->toIso8601String(),
    'image' => [asset('images/brand/og-logo.png')],
    'author' => ['@type' => 'Organization', 'name' => 'IPO Darbaar Research Desk', 'url' => route('about')],
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('icons/icon-512.png')]],
]" />
@endpush

@section('content')
<section class="page-head article-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('guides.index') }}">IPO Guide</a> <x-icon name="chevron-right" :size="13" />
            <span>{{ $guide['h1'] }}</span>
        </nav>
        <h1>{{ $guide['h1'] }}</h1>
        <div class="article-meta">
            <span><x-icon name="users" :size="15" /> IPO Darbaar Research Desk</span>
            <span><x-icon name="calendar" :size="15" /> Updated {{ $updated->format('j F Y') }}</span>
            <span><x-icon name="clock" :size="15" /> {{ $guide['minutes'] }} min read</span>
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container layout">
        <div class="stack">
            <article class="card card-pad prose guide" style="padding:30px">
                <p class="guide-summary">{{ $guide['summary'] }}</p>
                @include('guides.articles.'.$guide['slug'])
            </article>

            <x-faq :faqs="$guide['faqs']" :title="$guide['h1'].': FAQs'" />

            <p class="muted" style="font-size:12.5px">This guide is for education only and is not investment or tax advice. Rules change; we review our guides regularly and note the last update date above.</p>
        </div>

        <aside class="sidebar">
            @if ($related->isNotEmpty())
                <div class="card widget">
                    <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="file-text" :size="16" /></span> Related guides</div></div>
                    @foreach ($related as $item)
                        <a class="list-link" href="{{ route('guides.show', $item['slug']) }}">
                            <span class="ico"><x-icon :name="$item['icon']" :size="17" /></span>
                            <span><span class="t">{{ $item['h1'] }}</span><span class="m">{{ $item['minutes'] }} min read</span></span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="card widget">
                <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="layers" :size="16" /></span> Live IPO data</div></div>
                <a class="list-link" href="{{ route('ipos.current') }}"><span class="ico"><x-icon name="zap" :size="17" /></span><span><span class="t">Current IPOs open today</span></span></a>
                <a class="list-link" href="{{ route('ipos.gmp') }}"><span class="ico"><x-icon name="trending-up" :size="17" /></span><span><span class="t">IPO GMP today</span></span></a>
                <a class="list-link" href="{{ route('ipos.allotment') }}"><span class="ico"><x-icon name="ticket" :size="17" /></span><span><span class="t">IPO allotment status</span></span></a>
                <a class="list-link" href="{{ route('ipos.upcoming') }}"><span class="ico"><x-icon name="calendar" :size="17" /></span><span><span class="t">Upcoming IPOs</span></span></a>
            </div>

            <div class="card card-pad subscribe-card">
                <div class="card-title" style="margin-bottom:6px"><span class="ico"><x-icon name="bell" :size="16" /></span> Free IPO digest</div>
                <p class="muted" style="font-size:13.5px;margin-bottom:12px">Open IPOs, GMP moves and listing dates in your inbox.</p>
                <x-subscribe-form />
            </div>
        </aside>
    </div>
</div>
@endsection
