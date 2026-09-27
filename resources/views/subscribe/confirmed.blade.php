@extends('layouts.app')

@section('title', 'Subscription confirmed')
@section('robots', 'noindex, nofollow')

@section('content')
<div class="container error-page">
    <div class="empty-ico ok"><x-icon name="check-circle" :size="34" /></div>
    <h1 style="font-family:var(--font-display);font-size:30px;margin-top:14px">You're in!</h1>
    <p class="muted" style="margin-top:10px">
        {{ $subscriber->email }} will get the {{ $subscriber->frequency === 'weekly' ? 'weekly IPO roundup every Monday' : 'IPO digest every morning at 8 AM' }}.
        Every email has a one-click unsubscribe link.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:24px;flex-wrap:wrap">
        <a href="{{ route('ipos.gmp') }}" class="btn btn-gold"><x-icon name="trending-up" :size="16" /> See today's GMP</a>
        <a href="{{ route('home') }}" class="btn btn-outline">Back to home</a>
    </div>
</div>
@endsection
