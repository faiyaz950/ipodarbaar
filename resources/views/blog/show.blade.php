@extends('layouts.app')

@php
    use Illuminate\Support\Str;

    $url = $post->url();
    $categoryUrl = route('blog.show', $post->category);
    $image = $post->imageUrl() ?: asset('images/brand/og-logo.png');
    $author = $post->author;
    $modified = $post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at->copy()->addDay()) ? $post->updated_at : null;
    // IPOs placed in the text with [[ipo:slug]] already have a card; the rest are listed after the post,
    // unless there are so many (round-ups) that the post's own tables already cover them.
    $cardIpos = $post->ipos->reject(fn ($ipo) => str_contains((string) $post->body, '[[ipo:'.$ipo->slug.']]'));
    $cardIpos = $cardIpos->count() <= 6 ? $cardIpos : collect();
    $takeaways = $post->takeawayList();
@endphp

@if (mb_strlen($post->metaTitle()) > 50)
    @section('title_full', $post->metaTitle())
@else
    @section('title', $post->metaTitle())
@endif
@section('description', Str::limit($post->metaDescription(), 160))
@section('og_type', 'article')
@section('og_image', $image)
@unless ($post->isLive())
    @section('robots', 'noindex, nofollow')
@endunless

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/blog.css') }}?v={{ filemtime(public_path('assets/css/blog.css')) }}">
<link rel="alternate" type="application/rss+xml" title="IPO Darbaar Blog" href="{{ route('blog.feed') }}">
@if ($post->published_at)
<meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
@endif
<meta property="article:modified_time" content="{{ ($modified ?? $post->published_at ?? $post->updated_at)->toIso8601String() }}">
<meta property="article:section" content="{{ $post->categoryLabel() }}">
<x-jsonld :breadcrumbs="[['Home', route('home')], ['Blog', route('blog.index')], [$post->categoryLabel(), $categoryUrl], [$post->title, $url]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => Str::limit($post->title, 110, ''),
    'description' => $post->metaDescription(),
    'url' => $url,
    'mainEntityOfPage' => $url,
    'inLanguage' => 'en-IN',
    'datePublished' => $post->published_at?->toIso8601String(),
    'dateModified' => ($modified ?? $post->published_at ?? $post->updated_at)->toIso8601String(),
    'image' => [$image],
    'articleSection' => $post->categoryLabel(),
    'wordCount' => str_word_count(strip_tags((string) $post->body)),
    'author' => ['@type' => 'Organization', 'name' => $author->name, 'url' => $author->url()],
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('icons/icon-512.png')]],
    'about' => $post->ipos->map(fn ($ipo): array => ['@type' => 'Thing', 'name' => $ipo->name.' IPO', 'url' => $ipo->url()])->all(),
]" />
@endpush

@section('content')
<section class="page-head article-head blog-post-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('blog.index') }}">Blog</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ $categoryUrl }}">{{ $post->categoryLabel() }}</a>
        </nav>
        @unless ($post->isLive())
            <div class="blog-preview-note"><x-icon name="eye" :size="15" /> Preview: this post is {{ strtolower($post->statusLabel()) }}{{ $post->statusLabel() === 'Scheduled' ? ' for '.$post->published_at->format('j M Y, g:i A') : '' }} and only admins can see it.</div>
        @endunless
        <a class="blog-cat-pill" href="{{ $categoryUrl }}">{{ $post->categoryLabel() }}</a>
        <h1>{{ $post->title }}</h1>
        @if ($post->excerpt)
            <p class="lead">{{ $post->excerpt }}</p>
        @endif
        <div class="article-meta">
            <span><x-icon name="users" :size="15" /> By <a href="{{ $author->url() }}" class="blog-byline">{{ $author->name }}</a></span>
            @if ($post->published_at)
                <span><x-icon name="calendar" :size="15" /> {{ $post->published_at->format('j F Y') }}</span>
            @endif
            @if ($modified)
                <span><x-icon name="refresh" :size="15" /> Updated {{ $modified->format('j F Y') }}</span>
            @endif
            <span><x-icon name="clock" :size="15" /> {{ $post->reading_minutes }} min read</span>
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container layout">
        <div class="stack">
            <article class="card card-pad blog-article">
                @if ($post->imageUrl())
                    <figure class="article-banner blog-banner"><img src="{{ $post->imageUrl() }}" alt="{{ $post->image_alt ?: $post->title }}" fetchpriority="high"></figure>
                @endif

                @if ($takeaways !== [])
                    <div class="blog-takeaways">
                        <div class="blog-takeaways-title"><x-icon name="sparkles" :size="16" /> Key takeaways</div>
                        <ul>
                            @foreach ($takeaways as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (count($content['toc']) >= 3)
                    <details class="blog-toc" open>
                        <summary>In this post</summary>
                        <ol>
                            @foreach ($content['toc'] as $heading)
                                <li><a href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                @endif

                <div class="prose blog-prose">{!! $content['html'] !!}</div>

                @if ($cardIpos->isNotEmpty())
                    <h2 class="blog-section-title">IPOs in this post</h2>
                    <div class="blog-ipo-cards">
                        @foreach ($cardIpos as $ipo)
                            @include('blog._ipo-card', ['ipo' => $ipo])
                        @endforeach
                    </div>
                @endif

                <p class="blog-disclaimer">
                    @if ($post->category === 'ipo-reviews')
                        This review is for information and education only. It is not a recommendation to apply for, buy or sell any security. IPO Darbaar is not a SEBI-registered investment adviser. Grey market premium (GMP) is unofficial and can change quickly. Read the offer document (RHP) and consult a registered adviser before investing.
                    @else
                        For information and education only; not investment advice. Figures come from offer documents, exchange and SEBI filings and IPO Darbaar data as of the date of publishing. Grey market premium (GMP) is unofficial.
                    @endif
                </p>

                <div class="blog-share">
                    <div class="share-row">
                        <span class="lbl">Share</span>
                        <button type="button" class="sq-btn" data-share="whatsapp" data-title="{{ $post->title }}" data-url="{{ $url }}" aria-label="Share on WhatsApp"><x-icon name="message" :size="17" /></button>
                        <button type="button" class="sq-btn" data-share="x" data-title="{{ $post->title }}" data-url="{{ $url }}" aria-label="Share on X"><x-icon name="share" :size="17" /></button>
                        <button type="button" class="sq-btn" data-share="copy" data-url="{{ $url }}" aria-label="Copy link"><x-icon name="link" :size="17" /></button>
                    </div>
                </div>
            </article>

            <div class="card card-pad blog-author-box">
                <span class="blog-author-mark"><x-icon name="users" :size="22" /></span>
                <div>
                    <div class="blog-author-label">Written by</div>
                    <a href="{{ $author->url() }}" class="blog-author-name">{{ $author->name }}</a>
                    @if ($author->role)<div class="muted" style="font-size:13px">{{ $author->role }}</div>@endif
                    @if ($author->bio)<p>{{ $author->bio }}</p>@endif
                    <a class="link" href="{{ route('editorial-policy') }}">Our editorial policy</a>
                </div>
            </div>

            @if ($related->isNotEmpty())
                <section>
                    <h2 class="blog-section-title">Keep reading</h2>
                    <div class="blog-grid blog-grid-3">
                        @foreach ($related as $item)
                            @include('blog._card', ['post' => $item])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="sidebar">
            <div class="card widget">
                <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="file-text" :size="16" /></span> Blog sections</div></div>
                @foreach (\App\Models\BlogPost::CATEGORIES as $key => $info)
                    <a class="list-link" href="{{ route('blog.show', $key) }}">
                        <span><span class="t">{{ $info['label'] }}</span></span>
                        <span class="chev"><x-icon name="chevron-right" :size="15" /></span>
                    </a>
                @endforeach
            </div>

            <div class="card widget">
                <div class="card-head"><div class="card-title"><span class="ico"><x-icon name="layers" :size="16" /></span> Live IPO data</div></div>
                <a class="list-link" href="{{ route('ipos.current') }}"><span class="ico"><x-icon name="zap" :size="17" /></span><span><span class="t">Current IPOs open today</span></span></a>
                <a class="list-link" href="{{ route('ipos.gmp') }}"><span class="ico"><x-icon name="trending-up" :size="17" /></span><span><span class="t">IPO GMP today</span></span></a>
                <a class="list-link" href="{{ route('ipos.upcoming') }}"><span class="ico"><x-icon name="calendar" :size="17" /></span><span><span class="t">Upcoming IPOs</span></span></a>
                <a class="list-link" href="{{ route('ipos.allotment') }}"><span class="ico"><x-icon name="ticket" :size="17" /></span><span><span class="t">IPO allotment status</span></span></a>
            </div>

            <div class="card card-pad subscribe-card">
                <div class="card-title" style="margin-bottom:6px"><span class="ico"><x-icon name="bell" :size="16" /></span> Free IPO digest</div>
                <p class="muted" style="font-size:13.5px;margin-bottom:12px">New blog posts, open IPOs, GMP moves and listing dates in your inbox.</p>
                <x-subscribe-form />
            </div>
        </aside>
    </div>
</div>
@endsection
