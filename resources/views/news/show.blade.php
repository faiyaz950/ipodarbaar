@extends('layouts.app')

@section('title', $item['headline'])
@section('description', \Illuminate\Support\Str::limit($item['summary'], 158))
@section('og_type', 'article')
@section('og_image', $item['banner'] ?? '')

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => \Illuminate\Support\Str::limit($item['headline'], 110, ''),
    'image' => array_values(array_filter([$item['banner']])),
    'datePublished' => $item['date']?->toIso8601String(),
    'articleSection' => $item['category']['name'],
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<div data-lang-root>
<section class="page-head article-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('news.index') }}">News</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('news.index', ['category' => $item['category']['slug']]) }}">{{ $item['category']['name'] }}</a>
        </nav>
        <h1>
            <span data-lang-en>{{ $item['headline'] }}</span>
            <span data-lang-hi>{{ $item['headline_hi'] }}</span>
        </h1>
        <div class="article-meta">
            <span class="cat" style="--c: {{ $item['category']['color'] }}; filter: brightness(1.4)">{{ $item['category']['name'] }}</span>
            <span><x-icon name="calendar" :size="15" /> {{ $item['date']?->format('j F Y, g:i A') }}</span>
            <span><x-icon name="clock" :size="15" /> {{ $item['read_time'] }} min read</span>
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container layout">
        <article class="card card-pad" style="padding:28px">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:22px">
                <div class="lang-toggle" role="group" aria-label="Language">
                    <button type="button" data-lang-btn="en" class="active">English</button>
                    <button type="button" data-lang-btn="hi">Hinglish</button>
                </div>
                <div class="share-row">
                    <button type="button" class="sq-btn" data-share="whatsapp" data-title="{{ $item['headline'] }}" data-url="{{ $item['url'] }}" aria-label="Share on WhatsApp"><x-icon name="message" :size="17" /></button>
                    <button type="button" class="sq-btn" data-share="x" data-title="{{ $item['headline'] }}" data-url="{{ $item['url'] }}" aria-label="Share on X"><x-icon name="share" :size="17" /></button>
                    <button type="button" class="sq-btn" data-share="copy" data-url="{{ $item['url'] }}" aria-label="Copy link"><x-icon name="link" :size="17" /></button>
                </div>
            </div>

            @if ($item['banner'])
                <figure class="article-banner" style="margin:0 0 26px"><img src="{{ $item['banner'] }}" alt="{{ $item['headline'] }}"></figure>
            @endif

            <div class="prose" data-lang-en>{!! $item['html'] !!}</div>
            <div class="prose" data-lang-hi>{!! $item['html_hi'] !!}</div>

            <div class="note" style="margin-top:24px">
                <x-icon name="info" />
                <span>This news is for information only and is not investment advice. Please do your own research before making investment decisions.</span>
            </div>

            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:24px">
                <a href="{{ route('news.index') }}" class="btn btn-outline"><x-icon name="arrow-left" :size="16" /> All news</a>
                <a href="{{ route('news.shorts', ['category' => $item['category']['slug']]) }}" class="btn btn-navy"><x-icon name="smartphone" :size="16" /> More as Shorts</a>
            </div>
        </article>

        <aside class="sidebar">
            @if ($related->isNotEmpty())
            <div class="card widget">
                <div class="card-head">
                    <div class="card-title"><span class="ico"><x-icon name="newspaper" :size="16" /></span> More {{ $item['category']['name'] }}</div>
                </div>
                @foreach ($related as $n)
                    <a class="list-link" href="{{ $n['url'] }}">
                        @if ($n['image'])<img class="thumb" src="{{ $n['image'] }}" alt="" loading="lazy">@endif
                        <span><span class="t">{{ $n['headline'] }}</span><span class="m">{{ $n['date_label'] }}</span></span>
                    </a>
                @endforeach
            </div>
            @endif

            <div class="promo">
                <span class="badge" style="background:rgba(230,190,98,.18);color:var(--gold-2)"><x-icon name="activity" :size="13" /> Live</span>
                <h3>IPO GMP Today</h3>
                <p>See grey market premium and estimated listing gains for every active IPO.</p>
                <a href="{{ route('ipos.gmp') }}" class="btn btn-gold btn-block">View live GMP</a>
            </div>
        </aside>
    </div>
</div>
</div>
@endsection
