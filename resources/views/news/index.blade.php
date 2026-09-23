@extends('layouts.app')

@section('title', ($category ? $category['name'].' News' : 'Market News').' — Latest Updates')
@section('description', 'Latest '.strtolower($category['name'] ?? 'stock market').' news from India: IPOs, stock market, trading, commodities, crypto and investment updates in short.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('news.index') }}">News</a>
            @if ($category)<x-icon name="chevron-right" :size="13" /> <span>{{ $category['name'] }}</span>@endif
        </nav>
        <h1>{{ $category ? $category['name'].' News' : 'Market News' }}</h1>
        <p class="lead">Short, clear stories on what's moving Indian markets: IPOs, stocks, commodities, crypto and more.</p>
        <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap">
            <a href="{{ route('news.shorts', array_filter(['category' => $category['slug'] ?? null])) }}" class="btn btn-gold"><x-icon name="smartphone" :size="16" /> Read as Shorts</a>
        </div>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        <div class="chips">
            <a class="chip {{ ! $category ? 'active' : '' }}" href="{{ route('news.index') }}">All</a>
            @foreach ($categories as $c)
                <a class="chip {{ ($category['slug'] ?? null) === $c['slug'] ? 'active' : '' }}" href="{{ route('news.index', ['category' => $c['slug']]) }}">
                    <span class="cdot" style="background:{{ $c['color'] }}"></span>{{ $c['name'] }}
                </a>
            @endforeach
        </div>

        @if ($items->count())
            <div class="news-grid">
                @foreach ($items as $i => $item)
                    <x-news-card :item="$item" :feature="$i === 0 && $items->currentPage() === 1" />
                @endforeach
            </div>
            <div class="card">{{ $items->onEachSide(1)->links() }}</div>
        @else
            <div class="card empty">
                <div class="table-empty"><div class="ico"><x-icon name="newspaper" :size="22" /></div>
                    <h3>No news right now</h3><p>We couldn't load stories at the moment. Please try again shortly.</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
