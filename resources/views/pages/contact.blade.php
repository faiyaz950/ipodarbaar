@extends('layouts.app')

@inject('siteSettings', 'App\Support\Settings')

@php
    $email = config('ipodarbar.contact_email');
    $profiles = collect(\App\Http\Controllers\Admin\SettingsController::SOCIAL_NETWORKS)
        ->map(fn (string $label, string $network): array => ['label' => $label, 'url' => $siteSettings->get('social.'.$network)])
        ->filter(fn (array $profile): bool => filled($profile['url']));
@endphp

@section('title', 'Contact IPO Darbaar: Corrections, Feedback & Partnerships')
@section('description', 'Contact IPO Darbaar to report a correction in IPO data or news, share feedback or discuss advertising and partnerships.')

@push('head')
<x-jsonld :breadcrumbs="[['Home', route('home')], ['Contact', route('contact')]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'ContactPage',
    'name' => 'Contact IPO Darbaar',
    'url' => route('contact'),
    'mainEntity' => array_filter([
        '@type' => 'Organization',
        'name' => 'IPO Darbaar',
        'url' => route('home'),
        'email' => $email ?: null,
    ]),
]" />
@endpush

@section('content')
<section class="page-head">
    <div class="container">
        <nav class="crumbs"><a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" /> <span>Contact</span></nav>
        <h1>Contact IPO Darbaar</h1>
        <p class="lead">Found a wrong date, price or GMP? Have feedback or a partnership idea? We read every message.</p>
    </div>
</section>
<div class="page-body">
    <div class="container" style="max-width:900px">
        <div class="card card-pad prose" style="padding:32px">
            <h2 id="email">Email</h2>
            @if ($email)
                <p>Write to us at <a href="mailto:{{ $email }}">{{ $email }}</a>. We usually reply within two working days.</p>
            @else
                <p>Reach us through our official channels listed below.</p>
            @endif

            <h2 id="corrections">Report a correction</h2>
            <p>Accuracy matters to us. When you report an error, please include:</p>
            <ul>
                <li>the link of the page with the error;</li>
                <li>what is wrong and the correct figure or date;</li>
                <li>where the correct information can be checked, for example the RHP or an exchange notice.</li>
            </ul>
            <p>We check every report against primary sources and update the page. Read how we handle data and corrections in our <a href="{{ route('editorial-policy') }}">editorial policy</a>.</p>

            <h2 id="partnerships">Advertising and partnerships</h2>
            <p>For advertising, content partnerships or data queries, email us with "Partnership" in the subject line. Sponsored placements are always labelled on the site.</p>

            @if ($profiles->isNotEmpty())
                <h2 id="social">Follow IPO Darbaar</h2>
                <ul>
                    @foreach ($profiles as $profile)
                        <li><a href="{{ $profile['url'] }}" target="_blank" rel="noopener">{{ $profile['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif

            <h2 id="advice">Please note</h2>
            <p>We cannot advise on whether to apply for a particular IPO or on your personal investments. IPO Darbaar is an information platform and is not registered with SEBI as an investment adviser or research analyst. For help with an application, allotment or refund, contact your broker, bank or the IPO's registrar. Registrar links are on each IPO page and on the <a href="{{ route('ipos.allotment') }}">IPO allotment status</a> page.</p>
        </div>
    </div>
</div>
@endsection
