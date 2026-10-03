@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('description', 'How IPO Darbaar handles your data: email digests, polls, watchlists, cookies, analytics and advertising.')

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Privacy Policy</span></nav>
        <h1>Privacy Policy</h1>
    </div>
</section>
<div class="page-body">
    <div class="container" style="max-width:900px">
        <div class="card card-pad prose" style="padding:32px">
            <p>IPO Darbaar is a free information site. You can browse every page without an account. This page explains what little data we handle and why.</p>

            <h3>Email digest</h3>
            <p>If you subscribe, we store your email address, your chosen frequency (daily or weekly) and the time you confirmed. We send only the IPO digest you asked for. Every email has a one-click unsubscribe link, and unsubscribing stops all mail immediately. We never sell or share your email address.</p>

            <h3>Polls</h3>
            <p>When you vote in an IPO poll, we store your choice with a random identifier kept in a cookie on your device, plus a one-way hash of your IP address to limit repeated voting. We cannot identify you from these values.</p>

            <h3>Watchlist and preferences</h3>
            <p>Your watchlist, theme and language choice are saved only in your own browser (local storage). They never leave your device.</p>

            <h3>Cookies, analytics and advertising</h3>
            <p>We count page views ourselves without cookies: for each view we record the page, the referring website, the device type and browser, and a one-way code made from the date, your IP address and browser that changes every day. Your IP address itself is not stored, and the code can't be used to identify you or follow you across days. We use a session cookie needed for the site to work. We may use Google Analytics or Cloudflare Web Analytics to understand which pages are useful, and Google AdSense to show ads. These services may set their own cookies and process data such as your IP address and device information under their own privacy policies. You can control cookies through your browser settings, and manage Google ad personalisation at <a href="https://adssettings.google.com" target="_blank" rel="noopener">adssettings.google.com</a>.</p>

            <h3>Sponsored links</h3>
            <p>Some broker links are affiliate links and are labelled “Sponsored”. We may earn a commission if you open an account through them, at no extra cost to you.</p>

            <h3>Your choices</h3>
            <p>You can unsubscribe from emails at any time using the link in any digest. To ask us to delete your subscriber data, reply to any digest email.</p>

            <p class="muted">Last updated {{ now()->format('F Y') }}.</p>
        </div>
    </div>
</div>
@endsection
