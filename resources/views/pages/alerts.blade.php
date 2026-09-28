@extends('layouts.app')

@inject('siteSettings', 'App\Support\Settings')

@php
    $telegram = $siteSettings->get('social.telegram');
    $whatsapp = $siteSettings->get('social.whatsapp');
    $channels = array_merge($telegram ? ['Telegram'] : [], $whatsapp ? ['WhatsApp'] : [], ['Email']);
    $channelText = count($channels) > 1 ? implode(', ', array_slice($channels, 0, -1)).' & '.end($channels) : $channels[0];
    $faqs = [
        ['Are IPO Darbaar alerts free?', 'Yes. All alerts are free, and you can stop them at any time.'],
        ['What time do the alerts arrive?', ($telegram ? 'On Telegram, new IPOs are posted as soon as they are announced, the day\'s openings, closings, allotments and listings at 9 AM, and the GMP board at 6:30 PM. ' : '').'The email digest arrives at 8 AM, every day or every Monday, as you choose.'],
        ['How do I stop the email digest?', 'Every email has an unsubscribe link at the bottom. One click stops it.'],
        ['Do the alerts tell me which IPO to apply for?', 'No. Alerts share dates, GMP and news only. IPO Darbaar is not a SEBI-registered adviser and does not recommend IPOs.'],
    ];
@endphp

@section('title', 'Free IPO Alerts on '.$channelText.': Never Miss an IPO Date')
@section('description', 'Get free IPO alerts on '.$channelText.': new IPOs, opening and closing dates, allotment and listing days and the daily GMP board from IPO Darbaar.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['IPO Alerts', route('alerts')]]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>IPO Alerts</span></nav>
        <h1>IPO Alerts: Never Miss an IPO Date</h1>
        <p class="lead">New IPOs, opening and closing days, allotment and listing dates and the daily GMP board, delivered free on {{ $channelText }}.</p>
    </div>
</section>

<div class="page-body">
    <div class="container stack" style="max-width:960px">
        <div class="alert-grid">
            @if ($telegram)
                <div class="card card-pad alert-card">
                    <span class="ico ico-blue"><x-icon name="send" :size="20" /></span>
                    <h2>Telegram: instant alerts</h2>
                    <ul class="checklist">
                        <li>New IPO alerts as soon as dates are announced</li>
                        <li>9 AM: IPOs opening, closing, in allotment and listing today</li>
                        <li>6:30 PM: GMP board with the day's changes</li>
                    </ul>
                    <a class="btn btn-gold" href="{{ $telegram }}" target="_blank" rel="noopener"><x-icon name="send" :size="15" /> Join on Telegram</a>
                </div>
            @endif

            @if ($whatsapp)
                <div class="card card-pad alert-card">
                    <span class="ico ico-green"><x-icon name="message" :size="20" /></span>
                    <h2>WhatsApp Channel</h2>
                    <ul class="checklist">
                        <li>IPO updates in your WhatsApp Updates tab</li>
                        <li>Your number stays private: channels don't show followers' numbers</li>
                    </ul>
                    <a class="btn btn-gold" href="{{ $whatsapp }}" target="_blank" rel="noopener"><x-icon name="message" :size="15" /> Follow on WhatsApp</a>
                </div>
            @endif

            <div class="card card-pad alert-card" id="email">
                <span class="ico ico-gold"><x-icon name="mail" :size="20" /></span>
                <h2>Email: daily or weekly digest</h2>
                <ul class="checklist">
                    <li>IPOs opening and closing, allotment and listing dates</li>
                    <li>GMP changes since the previous day and top IPO news</li>
                    <li>8 AM every day, or every Monday. Unsubscribe in one click.</li>
                </ul>
                <x-subscribe-form />
            </div>

            <div class="card card-pad alert-card">
                <span class="ico ico-blue"><x-icon name="calendar" :size="20" /></span>
                <h2>Calendar: dates on your phone</h2>
                <ul class="checklist">
                    <li>Every IPO opening, closing, allotment and listing date</li>
                    <li>Works with Google Calendar, Apple Calendar and Outlook</li>
                    <li>Updates by itself as new IPOs are announced</li>
                </ul>
                <a class="btn btn-outline" href="{{ route('ipos.calendar') }}#subscribe"><x-icon name="calendar" :size="15" /> Subscribe to the IPO calendar</a>
            </div>

            <div class="card card-pad alert-card">
                <span class="ico ico-amber"><x-icon name="star" :size="20" /></span>
                <h2>Watchlist &amp; app</h2>
                <ul class="checklist">
                    <li>Star any IPO to see its dates in one place</li>
                    <li>Saved on this device, no sign-up needed</li>
                    <li>Install IPO Darbaar on your phone's home screen</li>
                </ul>
                <div class="btn-row">
                    <a class="btn btn-outline" href="{{ route('watchlist') }}"><x-icon name="star" :size="15" /> My watchlist</a>
                    <button type="button" class="btn btn-outline" data-install-app hidden><x-icon name="download" :size="15" /> Install app</button>
                </div>
            </div>
        </div>

        <x-faq :faqs="$faqs" title="IPO Alerts: FAQs" />
    </div>
</div>
@endsection
