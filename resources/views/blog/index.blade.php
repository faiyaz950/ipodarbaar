@extends('layouts.app')

@php
    use App\Models\BlogPost;

    $meta = $category ? BlogPost::CATEGORIES[$category] : null;
    $page = $posts->currentPage();
    $suffix = $page > 1 ? ' – Page '.$page : '';
    $listUrl = $category ? route('blog.show', $category) : route('blog.index');
    $showFeature = $page === 1 && $posts->count() > 0;
@endphp

@section('title', ($meta ? $meta['title'] : 'IPO Blog: Reviews, Weekly Wrap & Market Insights').$suffix)
@section('description', $meta ? $meta['description'] : 'The IPO Darbaar blog: in-depth IPO reviews, a weekly IPO market wrap, listing day recaps, data-led trends and explainers from the IPO Darbaar Research Desk.')
@if ($posts->total() === 0)
    @section('robots', 'noindex, follow')
@endif

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/blog.css') }}?v={{ filemtime(public_path('assets/css/blog.css')) }}">
<link rel="alternate" type="application/rss+xml" title="IPO Darbaar Blog" href="{{ route('blog.feed') }}">
<x-jsonld :breadcrumbs="$meta ? [['Home', route('home')], ['Blog', route('blog.index')], [$meta['label'], $listUrl]] : [['Home', route('home')], ['Blog', route('blog.index')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'Blog',
    'name' => $meta ? 'IPO Darbaar Blog: '.$meta['label'] : 'IPO Darbaar Blog',
    'url' => $listUrl,
    'publisher' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home')],
    'blogPost' => $posts->getCollection()->map(fn (BlogPost $post): array => [
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'url' => $post->url(),
        'datePublished' => $post->published_at?->toIso8601String(),
    ])->all(),
]" />
@endpush

@section('content')
<section class="page-head blog-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            @if ($meta)
                <a href="{{ route('blog.index') }}">Blog</a> <x-icon name="chevron-right" :size="13" /> <span>{{ $meta['label'] }}</span>
            @else
                <span>Blog</span>
            @endif
        </nav>
        <h1>{{ $meta ? $meta['label'] : 'IPO Darbaar Blog' }}</h1>
        <p class="lead">{{ $meta ? $meta['description'] : 'In-depth IPO reviews, the weekly IPO wrap, listing day recaps and the numbers behind the market, from the IPO Darbaar Research Desk.' }}</p>
        <div class="blog-chips">
            <a class="chip {{ $category === null ? 'active' : '' }}" href="{{ route('blog.index') }}">All posts</a>
            @foreach (BlogPost::CATEGORIES as $key => $info)
                <a class="chip {{ $category === $key ? 'active' : '' }}" href="{{ route('blog.show', $key) }}">{{ $info['label'] }}@if (($counts[$key] ?? 0) > 0) <span class="count">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container">
        @if ($posts->isEmpty())
            <div class="card card-pad blog-empty">
                <h2>New posts are on the way</h2>
                <p class="muted">The Research Desk is writing. Meanwhile, read our <a class="link" href="{{ route('guides.index') }}">IPO guides</a> or follow the <a class="link" href="{{ route('ipos.gmp') }}">live IPO GMP</a>.</p>
            </div>
        @else
            <div class="blog-grid">
                @foreach ($posts as $post)
                    @include('blog._card', ['post' => $post, 'feature' => $showFeature && $loop->first])
                @endforeach
            </div>
            @if ($posts->hasPages())
                <div style="margin-top:28px">{{ $posts->onEachSide(1)->links() }}</div>
            @endif
        @endif

        <div class="card card-pad blog-subscribe">
            <div>
                <h2 class="card-title" style="margin-bottom:4px"><span class="ico"><x-icon name="bell" :size="16" /></span> Get new posts and the IPO digest by email</h2>
                <p class="muted" style="margin:0">Free, no spam, one-click unsubscribe. Or follow the <a class="link" href="{{ route('blog.feed') }}">RSS feed</a>.</p>
            </div>
            <x-subscribe-form compact />
        </div>
    </div>
</div>
@endsection
