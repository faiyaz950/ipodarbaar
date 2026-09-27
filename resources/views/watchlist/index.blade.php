@extends('layouts.app')

@section('title', 'My IPO Watchlist')
@section('description', 'Your starred IPOs with upcoming open, close, allotment and listing dates.')
@section('robots', 'noindex, follow')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Watchlist</span></nav>
        <h1>My Watchlist</h1>
        <p class="lead">Tap <x-icon name="star" :size="15" /> on any IPO to follow it here. Your list is saved on this device only, no login needed.</p>
    </div>
</section>

<div class="page-body">
    <div class="container" data-watchlist data-url="{{ route('watchlist.items') }}">
        <div class="card empty" data-watchlist-empty hidden>
            <div class="table-empty">
                <div class="ico"><x-icon name="star" :size="22" /></div>
                <h3>No IPOs in your watchlist yet</h3>
                <p>Star the IPOs you're interested in to see their key dates in one place.</p>
                <div style="display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap">
                    <a href="{{ route('ipos.index', ['status' => 'open']) }}" class="btn btn-gold btn-sm">Browse open IPOs</a>
                    <a href="{{ route('ipos.index', ['status' => 'upcoming']) }}" class="btn btn-outline btn-sm">Upcoming IPOs</a>
                </div>
            </div>
        </div>
        <div data-watchlist-items>
            <div class="card card-pad"><div class="spinner" style="margin:24px auto"></div></div>
        </div>
    </div>
</div>
@endsection
