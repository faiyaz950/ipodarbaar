@extends('layouts.app')

@section('title', $q !== '' ? 'Search: '.$q : 'Search IPO Darbaar')
@section('description', 'Search IPOs, IPO lists, guides, calculators and market news on IPO Darbaar.')
@section('robots', 'noindex, follow')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Search</span></nav>
        <h1>{{ $q !== '' ? 'Results for “'.$q.'”' : 'Search IPO Darbaar' }}</h1>
        <form class="search-page-form" action="{{ route('search') }}" method="get" role="search">
            <label class="input-icon" style="flex:1">
                <x-icon name="search" :size="17" />
                <span class="sr-only">Search</span>
                <input class="input" type="search" name="q" value="{{ $q }}" placeholder="IPO name, guide, calculator or news" autofocus>
            </label>
            <button type="submit" class="btn btn-gold">Search</button>
        </form>
        @if ($q !== '')
            <span class="updated-line">{{ $total }} {{ $total === 1 ? 'result' : 'results' }}</span>
        @endif
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        @if ($q !== '' && $total === 0)
            <div class="card card-pad">
                <h2 class="card-title" style="margin-bottom:8px">Nothing found for “{{ $q }}”</h2>
                <p class="muted">Check the spelling or try a shorter word, such as a company name. You can also browse:</p>
                <div class="hub-links" style="margin-top:12px">
                    <a class="chip" href="{{ route('ipos.current') }}">Current IPOs</a>
                    <a class="chip" href="{{ route('ipos.upcoming') }}">Upcoming IPOs</a>
                    <a class="chip" href="{{ route('ipos.gmp') }}">IPO GMP Today</a>
                    <a class="chip" href="{{ route('guides.index') }}">IPO Guide</a>
                    <a class="chip" href="{{ route('calculators.index') }}">Calculators</a>
                </div>
            </div>
        @endif

        @if ($results['ipos']->isNotEmpty())
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title"><span class="ico"><x-icon name="layers" :size="16" /></span> IPOs</h2>
                    <a class="link-gold" href="{{ route('ipos.index', ['q' => $q]) }}">All matching IPOs <x-icon name="arrow-right" :size="14" /></a>
                </div>
                <x-ipo-table :ipos="$results['ipos']" />
            </div>
        @endif

        @if ($results['pages']->isNotEmpty() || $results['calculators']->isNotEmpty())
            <div class="grid-2">
                @if ($results['pages']->isNotEmpty())
                    <div class="card">
                        <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon name="grid" :size="16" /></span> IPO lists &amp; tools</h2></div>
                        @foreach ($results['pages'] as $page)
                            <a class="admin-row" href="{{ $page['url'] }}"><span>{{ $page['title'] }}</span><x-icon name="arrow-right" :size="14" /></a>
                        @endforeach
                    </div>
                @endif
                @if ($results['calculators']->isNotEmpty())
                    <div class="card">
                        <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon name="calculator" :size="16" /></span> Calculators</h2></div>
                        @foreach ($results['calculators'] as $calc)
                            <a class="admin-row" href="{{ route('calculators.show', $calc['slug']) }}"><span>{{ $calc['name'] }}<small class="muted">{{ $calc['short'] }}</small></span><x-icon name="arrow-right" :size="14" /></a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($results['guides']->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2 class="card-title"><span class="ico"><x-icon name="file-text" :size="16" /></span> Guides</h2></div>
                @foreach ($results['guides'] as $guide)
                    <a class="admin-row" href="{{ route('guides.show', $guide['slug']) }}"><span>{{ $guide['h1'] }}<small class="muted">{{ $guide['summary'] }}</small></span><x-icon name="arrow-right" :size="14" /></a>
                @endforeach
            </div>
        @endif

        @if ($results['news']->isNotEmpty())
            <div>
                <h2 class="section-title" style="font-size:22px;margin-bottom:14px">News</h2>
                <div class="news-grid">
                    @foreach ($results['news'] as $item)
                        <x-news-card :item="$item" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
