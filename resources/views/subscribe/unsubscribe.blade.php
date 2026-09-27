@extends('layouts.app')

@section('title', $done ? 'Unsubscribed' : 'Unsubscribe')
@section('robots', 'noindex, nofollow')

@section('content')
<div class="container error-page">
    @if ($done)
        <div class="empty-ico"><x-icon name="mail" :size="34" /></div>
        <h1 style="font-family:var(--font-display);font-size:30px;margin-top:14px">You've been unsubscribed</h1>
        <p class="muted" style="margin-top:10px">{{ $subscriber->email }} won't receive any more IPO Darbaar digests. Changed your mind? You can sign up again anytime.</p>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:24px;flex-wrap:wrap">
            <a href="{{ route('home') }}" class="btn btn-outline">Back to home</a>
        </div>
    @else
        <div class="empty-ico"><x-icon name="mail" :size="34" /></div>
        <h1 style="font-family:var(--font-display);font-size:30px;margin-top:14px">Unsubscribe from the digest?</h1>
        <p class="muted" style="margin-top:10px">{{ $subscriber->email }} will stop receiving IPO Darbaar emails.</p>
        <form method="post" action="{{ $action }}" style="margin-top:24px">
            @csrf
            <button type="submit" class="btn btn-navy">Yes, unsubscribe me</button>
        </form>
    @endif
</div>
@endsection
