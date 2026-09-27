@extends('layouts.app')

@section('title', 'You are offline')
@section('robots', 'noindex, nofollow')
@section('hide_ticker', '1')

@section('content')
<div class="container error-page">
    <div class="empty-ico"><x-icon name="wifi-off" :size="34" /></div>
    <h1 style="font-family:var(--font-display);font-size:30px;margin-top:14px">You're offline</h1>
    <p class="muted" style="margin-top:10px">Pages you opened recently are still available. Reconnect to see live GMP and the latest IPO updates.</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:24px;flex-wrap:wrap">
        <button type="button" class="btn btn-gold" onclick="location.reload()"><x-icon name="refresh" :size="16" /> Try again</button>
        <a href="{{ route('watchlist') }}" class="btn btn-outline"><x-icon name="star" :size="16" /> My watchlist</a>
    </div>
</div>
@endsection
