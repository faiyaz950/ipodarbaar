@extends('layouts.app')

@section('title', 'News Shorts: Market News in 60 Seconds')
@section('description', 'Swipe through bite-sized stock market, IPO, commodity and crypto news in English or Hinglish.')
@section('body_class', 'shorts-page')
@section('hide_ticker', '1')

@section('content')
<div class="container" data-lang-root>
    <div class="shorts-shell">
        <aside class="shorts-side left">
            <span class="eyebrow">IPO Darbaar Shorts</span>
            <h1>Market news<br>in 60 seconds</h1>
            <p>Swipe up for the next story. Switch between English and Hinglish anytime.</p>
            <nav class="shorts-cats" aria-label="Categories">
                <a href="{{ route('news.shorts') }}" class="{{ ! $category ? 'active' : '' }}">All stories</a>
                @foreach ($categories as $c)
                    <a href="{{ route('news.shorts', ['category' => $c['slug']]) }}" class="{{ ($category['slug'] ?? null) === $c['slug'] ? 'active' : '' }}">{{ $c['name'] }}</a>
                @endforeach
            </nav>
        </aside>

        <div class="shorts-wrap">
            <div class="shorts-top">
                <div class="shorts-chips">
                    <a href="{{ route('news.shorts') }}" class="{{ ! $category ? 'active' : '' }}">All</a>
                    @foreach ($categories as $c)
                        <a href="{{ route('news.shorts', ['category' => $c['slug']]) }}" class="{{ ($category['slug'] ?? null) === $c['slug'] ? 'active' : '' }}">{{ $c['name'] }}</a>
                    @endforeach
                </div>
                <span class="lang-toggle" style="flex-shrink:0; background:#16224A">
                    <button type="button" data-lang-btn="en" class="active">EN</button>
                    <button type="button" data-lang-btn="hi">Hinglish</button>
                </span>
            </div>

            <div class="shorts-feed" data-shorts-feed
                 data-feed-url="{{ route('news.feed') }}"
                 data-category="{{ $category['slug'] ?? '' }}"
                 data-next-page="2"
                 data-has-more="{{ $hasMore ? '1' : '0' }}"
                 data-start-id="{{ $startId }}">
                @forelse ($items as $item)
                    @include('news._short', ['item' => $item])
                @empty
                    <div class="shorts-loader"><div style="text-align:center"><x-icon name="newspaper" :size="32" /><p style="margin-top:10px">No stories available right now.</p></div></div>
                @endforelse
                @if ($hasMore)
                    <div class="shorts-loader" data-loader><div class="spinner"></div></div>
                @endif
            </div>
        </div>

        <aside class="shorts-side right">
            <div class="lang-toggle" role="group" aria-label="Language" style="background:#16224A">
                <button type="button" data-lang-btn="en" class="active">English</button>
                <button type="button" data-lang-btn="hi">Hinglish</button>
            </div>
            <div class="shorts-nav">
                <button type="button" class="icon-btn" data-shorts-prev aria-label="Previous story"><x-icon name="chevron-up" :size="22" /></button>
                <button type="button" class="icon-btn" data-shorts-next aria-label="Next story"><x-icon name="chevron-down" :size="22" /></button>
            </div>
            <div class="shorts-kbd"><kbd>↑</kbd><kbd>↓</kbd> to navigate</div>
            <a href="{{ route('news.index') }}" class="btn btn-ghost-light btn-sm" style="align-self:flex-start"><x-icon name="grid" :size="15" /> Grid view</a>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/shorts.js') }}?v={{ filemtime(public_path('assets/js/shorts.js')) }}" defer></script>
@endpush
