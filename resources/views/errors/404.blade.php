@extends('layouts.app')

@section('title', 'Page not found')

@section('content')
<div class="container error-page">
    <div class="code">404</div>
    <h1 style="font-family:var(--font-display);font-size:30px;margin-top:10px">This page has left the Darbaar</h1>
    <p class="muted" style="margin-top:10px">The IPO or page you're looking for doesn't exist or may have moved.</p>
    <div style="display:flex;gap:10px;justify-content:center;margin-top:24px;flex-wrap:wrap">
        <a href="{{ route('home') }}" class="btn btn-gold"><x-icon name="home" :size="16" /> Go home</a>
        <a href="{{ route('ipos.index') }}" class="btn btn-outline"><x-icon name="layers" :size="16" /> Browse IPOs</a>
    </div>
</div>
@endsection
