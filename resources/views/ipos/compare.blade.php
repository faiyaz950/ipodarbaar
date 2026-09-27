@extends('layouts.app')

@php
    use App\Http\Controllers\CompareController;
    $canAdd = count($slugs) < CompareController::MAX_IPOS;
    $addUrl = route('compare', ['ipos' => implode(',', [...$slugs, '__SLUG__'])]);
@endphp

@section('title', $ipos->isEmpty() ? 'Compare IPOs' : 'Compare '.$ipos->pluck('name')->implode(' vs ').' IPO')
@section('description', 'Compare IPOs side by side: price band, lot size, GMP, subscription, listing gain and company financials.')
@section('robots', 'noindex, follow')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <a href="{{ route('ipos.index') }}">IPOs</a> <x-icon name="chevron-right" :size="13" /> <span>Compare</span></nav>
        <h1>Compare IPOs</h1>
        <p class="lead">Put up to {{ CompareController::MAX_IPOS }} IPOs side by side. The better figure in each row is highlighted.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack">
        @if ($canAdd)
            <div class="card card-pad">
                <form class="search compare-picker" action="{{ route('ipos.index') }}" method="get" role="search" data-search data-compare-url="{{ $addUrl }}">
                    <label class="search-field">
                        <x-icon name="plus" :size="17" />
                        <input type="search" name="q" placeholder="{{ $ipos->isEmpty() ? 'Search an IPO to compare…' : 'Add another IPO…' }}" autocomplete="off" aria-label="Add an IPO to compare" data-search-input>
                    </label>
                    <div class="suggest" data-suggest></div>
                </form>
            </div>
        @endif

        @if ($ipos->isEmpty())
            <div class="card empty">
                <div class="table-empty">
                    <div class="ico"><x-icon name="columns" :size="22" /></div>
                    <h3>Pick IPOs to compare</h3>
                    <p>Search above, or use the Compare button on any IPO page.</p>
                </div>
            </div>
        @else
            <div class="card">
                <div class="table-wrap">
                    <table class="table compare-table">
                        <thead>
                            <tr>
                                <th>IPO</th>
                                @foreach ($ipos as $ipo)
                                    <th>
                                        <div class="co">
                                            <x-logo-tile :ipo="$ipo" />
                                            <a href="{{ $ipo->url() }}" class="co-name">{{ $ipo->name }}</a>
                                        </div>
                                        <a class="compare-remove" href="{{ route('compare', array_filter(['ipos' => implode(',', array_diff($slugs, [$ipo->slug]))])) }}" aria-label="Remove {{ $ipo->name }}"><x-icon name="x" :size="13" /> Remove</a>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><b>{{ $row['label'] }}</b></td>
                                    @foreach ($row['cells'] as $index => $cell)
                                        <td class="{{ in_array($index, $row['best'], true) ? 'best' : '' }}">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="note">
                <x-icon name="alert" />
                <span>GMP and visitor data are unofficial and indicative. Highlighting shows the higher (or, for P/E and debt, lower) figure only and is not a recommendation.</span>
            </div>
        @endif
    </div>
</div>
@endsection
